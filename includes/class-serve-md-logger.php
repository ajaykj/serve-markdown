<?php
/**
 * Crawler request logger.
 *
 * Stores Markdown requests in a custom DB table so site owners can see
 * which AI bots are consuming their content.
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

final class Serve_MD_Logger {

	private static ?self $instance = null;

	/** Custom table name (without prefix). */
	const TABLE = 'serve_md_log';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Create / upgrade the log table.
	 */
	public static function create_log_table(): void {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		// Use CREATE TABLE IF NOT EXISTS instead of dbDelta to avoid
		// a dependency on ABSPATH for requiring wp-admin/includes/upgrade.php.
		// This keeps the plugin compatible with WordPress.com and managed hosts.
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			url        TEXT            NOT NULL,
			user_agent VARCHAR(512)    NOT NULL DEFAULT '',
			bot_name   VARCHAR(100)    NOT NULL DEFAULT '',
			method     VARCHAR(10)     NOT NULL DEFAULT '',
			ip_address VARCHAR(45)     NOT NULL DEFAULT '',
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_created (created_at),
			KEY idx_bot (bot_name)
		) {$charset};";

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Drop the log table.
	 */
	public static function drop_log_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Log a Markdown request.
	 *
	 * @param int    $post_id  Post ID being served.
	 * @param string $method   "url" or "header" — how the request was triggered.
	 */
	public function log_request( int $post_id, string $method ): void {
		$settings = get_option( 'serve_md_settings', [] );
		if ( empty( $settings['enable_log'] ) ) {
			return;
		}

		global $wpdb;

		$ua       = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$bot_name = $this->detect_bot_name( $ua );
		$url      = sanitize_url( $_SERVER['REQUEST_URI'] ?? '' );
		$ip       = $this->get_client_ip();

		$wpdb->insert(
			$wpdb->prefix . self::TABLE,
			[
				'post_id'    => $post_id,
				'url'        => $url,
				'user_agent' => $ua,
				'bot_name'   => $bot_name,
				'method'     => $method,
				'ip_address' => $ip,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		// Prune old entries beyond retention limit.
		$this->maybe_prune_expired_entries();

		// Enforce row-count and table-size caps.
		$this->maybe_enforce_limits();
	}

	/**
	 * Get log entries.
	 *
	 * @param int    $per_page Rows per page.
	 * @param int    $page     Current page (1-based).
	 * @param string $bot      Filter by bot name (empty = all).
	 * @return object{ rows: array, total: int }
	 */
	public function get_log_entries( int $per_page = 30, int $page = 1, string $bot = '' ): object {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$where  = '';
		$params = [];

		if ( $bot !== '' ) {
			$where    = 'WHERE bot_name = %s';
			$params[] = $bot;
		}

		$total_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = $bot !== ''
			? (int) $wpdb->get_var( $wpdb->prepare( $total_sql, ...$params ) ) // phpcs:ignore
			: (int) $wpdb->get_var( $total_sql ); // phpcs:ignore

		$offset    = max( 0, ( $page - 1 ) * $per_page );
		$query_sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		$query_params   = $bot !== '' ? [ ...$params, $per_page, $offset ] : [ $per_page, $offset ];
		$rows           = $wpdb->get_results( $wpdb->prepare( $query_sql, ...$query_params ) ); // phpcs:ignore

		return (object) [ 'rows' => $rows, 'total' => $total ];
	}

	/**
	 * Get summary stats.
	 *
	 * @return array{ total: int, bots: array, today: int }
	 */
	public function get_log_stats(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
		$today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", // phpcs:ignore
			current_time( 'Y-m-d' ) . ' 00:00:00'
		) );

		$bots = $wpdb->get_results(
			"SELECT bot_name, COUNT(*) as cnt FROM {$table} GROUP BY bot_name ORDER BY cnt DESC", // phpcs:ignore
			ARRAY_A
		);

		return [
			'total' => $total,
			'today' => $today,
			'bots'  => $bots ?: [],
		];
	}

	/**
	 * Delete all log entries.
	 */
	public function clear_log(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}" . self::TABLE ); // phpcs:ignore
	}

	/**
	 * Detect known AI bot from user-agent string.
	 */
	private function detect_bot_name( string $ua ): string {
		$bots = [
			'ClaudeBot'       => 'ClaudeBot',
			'claude-web'      => 'ClaudeBot',
			'GPTBot'          => 'GPTBot',
			'ChatGPT-User'    => 'ChatGPT',
			'OAI-SearchBot'   => 'OAI-SearchBot',
			'Google-Extended' => 'Google AI',
			'Googlebot'       => 'Googlebot',
			'Bingbot'         => 'Bingbot',
			'bingbot'         => 'Bingbot',
			'PerplexityBot'   => 'PerplexityBot',
			'YouBot'          => 'YouBot',
			'CCBot'           => 'CCBot',
			'cohere-ai'       => 'Cohere',
			'Applebot'        => 'Applebot',
			'Bytespider'      => 'Bytespider',
			'Meta-ExternalAgent' => 'Meta AI',
		];

		foreach ( $bots as $needle => $name ) {
			if ( str_contains( $ua, $needle ) ) {
				return $name;
			}
		}

		// If it looks like a bot but isn't in our list.
		if ( preg_match( '/bot|crawl|spider|agent|scraper/i', $ua ) ) {
			return 'Other Bot';
		}

		return 'Browser / Unknown';
	}

	/**
	 * Get the client IP address.
	 */
	private function get_client_ip(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Enforce max-entries and max-size caps.
	 * Runs at most once per 5 minutes via a transient guard.
	 */
	private function maybe_enforce_limits(): void {
		if ( get_transient( 'serve_md_limits_guard' ) ) {
			return;
		}

		set_transient( 'serve_md_limits_guard', 1, 5 * MINUTE_IN_SECONDS );

		$settings    = get_option( 'serve_md_settings', [] );
		$max_entries = (int) ( $settings['log_max_entries'] ?? 10000 );
		$max_size_mb = (int) ( $settings['log_max_size_mb'] ?? 50 );

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		// Row count cap.
		if ( $max_entries > 0 ) {
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
			if ( $count > $max_entries ) {
				$excess = $count - $max_entries;
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$table} ORDER BY created_at ASC LIMIT %d", // phpcs:ignore
					$excess
				) );
			}
		}

		// Table size cap.
		if ( $max_size_mb > 0 ) {
			$max_bytes  = $max_size_mb * 1024 * 1024;
			$data_bytes = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT data_length FROM information_schema.tables WHERE table_schema = %s AND table_name = %s", // phpcs:ignore
				DB_NAME,
				$table
			) );

			if ( $data_bytes > $max_bytes ) {
				// Delete oldest 10% of rows and re-check once.
				$count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
				$delete = max( 1, (int) ceil( $count * 0.1 ) );
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$table} ORDER BY created_at ASC LIMIT %d", // phpcs:ignore
					$delete
				) );
			}
		}
	}

	/**
	 * Prune entries older than the configured retention period.
	 * Runs at most once per hour via a transient guard.
	 */
	private function maybe_prune_expired_entries(): void {
		if ( get_transient( 'serve_md_prune_guard' ) ) {
			return;
		}

		set_transient( 'serve_md_prune_guard', 1, HOUR_IN_SECONDS );

		$settings  = get_option( 'serve_md_settings', [] );
		$days      = (int) ( $settings['log_retention_days'] ?? 30 );

		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore
			gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
		) );
	}
}
