<?php
/**
 * Admin settings page with tabs:
 *   General | Frontmatter | Exclusions | Crawler Log
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

final class Serve_MD_Admin {

	private static ?self $instance = null;
	private string $page_slug = 'serve-markdown';

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_post_serve_md_clear_log', [ $this, 'handle_clear_log' ] );
	}

	public function add_settings_menu(): void {
		add_options_page(
			'Serve Markdown Settings',
			'Serve Markdown',
			'manage_options',
			$this->page_slug,
			[ $this, 'render_settings_page' ]
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( $hook !== 'settings_page_' . $this->page_slug ) {
			return;
		}
		wp_enqueue_style(
			'serve-markdown-admin',
			SERVE_MD_URL . 'assets/admin.css',
			[],
			SERVE_MD_VERSION
		);
	}

	/**
	 * Register all settings in a single option: serve_md_settings.
	 */
	public function register_settings(): void {
		register_setting( 'serve_md_settings_group', 'serve_md_settings', [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
			'default'           => self::default_settings(),
		] );
	}

	public static function default_settings(): array {
		return [
			// General.
			'enable_content_negotiation' => 1,
			'enable_md_url'              => 1,
			'enable_discovery_link'      => 1,
			'post_types'                 => [ 'post', 'page' ],

			// Frontmatter.
			'fm_url'        => 1,
			'fm_title'      => 1,
			'fm_author'     => 1,
			'fm_date'       => 1,
			'fm_modified'   => 1,
			'fm_type'       => 1,
			'fm_summary'    => 1,
			'fm_categories' => 1,
			'fm_tags'       => 1,
			'fm_image'      => 1,
			'fm_published'  => 1,
			'custom_fields' => '',  // newline-separated key:value pairs
			'meta_keys'     => '',  // newline-separated post meta keys

			// Exclusions.
			'exclude_categories' => [], // term IDs
			'exclude_tags'       => [], // term IDs

			// Logging.
			'enable_log'         => 1,
			'log_retention_days' => 30,
			'log_max_entries'    => 10000,
			'log_max_size_mb'    => 50,
		];
	}

	public function sanitize_settings( $input ): array {
		$clean = self::default_settings();

		// Checkboxes — absent means 0.
		foreach ( [ 'enable_content_negotiation', 'enable_md_url', 'enable_discovery_link', 'enable_log' ] as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		// Frontmatter toggles.
		foreach ( [ 'fm_url', 'fm_title', 'fm_author', 'fm_date', 'fm_modified', 'fm_type', 'fm_summary', 'fm_categories', 'fm_tags', 'fm_image', 'fm_published' ] as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		// Post types.
		$clean['post_types'] = [];
		if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$clean['post_types'] = array_map( 'sanitize_key', $input['post_types'] );
		}

		// Text areas.
		$clean['custom_fields'] = sanitize_textarea_field( $input['custom_fields'] ?? '' );
		$clean['meta_keys']     = sanitize_textarea_field( $input['meta_keys'] ?? '' );

		// Exclusion term IDs.
		$clean['exclude_categories'] = array_map( 'absint', $input['exclude_categories'] ?? [] );
		$clean['exclude_tags']       = array_map( 'absint', $input['exclude_tags'] ?? [] );

		// Retention & limits.
		$clean['log_retention_days'] = absint( $input['log_retention_days'] ?? 30 );
		$clean['log_max_entries']    = absint( $input['log_max_entries'] ?? 10000 );
		$clean['log_max_size_mb']    = absint( $input['log_max_size_mb'] ?? 50 );

		// Flush rewrite rules when post types change.
		set_transient( 'serve_md_flush_rewrite', 1, 60 );

		return $clean;
	}

	/**
	 * Handle "Clear Log" form submission.
	 */
	public function handle_clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'serve_md_clear_log' );

		Serve_MD_Logger::instance()->clear_log();

		wp_safe_redirect( add_query_arg( [
			'page' => $this->page_slug,
			'tab'  => 'log',
			'cleared' => '1',
		], admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab      = sanitize_key( $_GET['tab'] ?? 'general' );
		$settings = wp_parse_args( get_option( 'serve_md_settings', [] ), self::default_settings() );
		?>
		<div class="wrap serve-md-wrap">
			<h1>Serve Markdown Settings</h1>

			<nav class="nav-tab-wrapper serve-md-tabs">
				<a href="<?php echo esc_url( $this->get_tab_url( 'general' ) ); ?>"
				   class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'frontmatter' ) ); ?>"
				   class="nav-tab <?php echo $tab === 'frontmatter' ? 'nav-tab-active' : ''; ?>">Frontmatter</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'exclusions' ) ); ?>"
				   class="nav-tab <?php echo $tab === 'exclusions' ? 'nav-tab-active' : ''; ?>">Exclusions</a>
				<a href="<?php echo esc_url( $this->get_tab_url( 'log' ) ); ?>"
				   class="nav-tab <?php echo $tab === 'log' ? 'nav-tab-active' : ''; ?>">Crawler Log</a>
			</nav>

			<?php
			switch ( $tab ) {
				case 'frontmatter':
					$this->render_frontmatter_tab( $settings );
					break;
				case 'exclusions':
					$this->render_exclusions_tab( $settings );
					break;
				case 'log':
					$this->render_log_tab( $settings );
					break;
				default:
					$this->render_general_tab( $settings );
					break;
			}
			?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Tab: General
	 * ----------------------------------------------------------------*/

	private function render_general_tab( array $s ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'serve_md_settings_group' ); ?>
			<?php $this->preserve_other_tabs( $s, 'general' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Features</th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="serve_md_settings[enable_content_negotiation]" value="1" <?php checked( $s['enable_content_negotiation'] ); ?>>
								Content Negotiation <code>Accept: text/markdown</code>
							</label><br>
							<label>
								<input type="checkbox" name="serve_md_settings[enable_md_url]" value="1" <?php checked( $s['enable_md_url'] ); ?>>
								<code>.md</code> URL suffix
							</label><br>
							<label>
								<input type="checkbox" name="serve_md_settings[enable_discovery_link]" value="1" <?php checked( $s['enable_discovery_link'] ); ?>>
								Auto-discovery <code>&lt;link rel="alternate"&gt;</code> tag
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">Post Types</th>
					<td>
						<fieldset>
							<?php
							$all_types = get_post_types( [ 'public' => true ], 'objects' );
							foreach ( $all_types as $pt ) :
								if ( $pt->name === 'attachment' ) continue;
								$checked = in_array( $pt->name, $s['post_types'], true );
								?>
								<label>
									<input type="checkbox" name="serve_md_settings[post_types][]"
										   value="<?php echo esc_attr( $pt->name ); ?>"
										   <?php checked( $checked ); ?>>
									<?php echo esc_html( $pt->labels->singular_name ); ?>
									<code>(<?php echo esc_html( $pt->name ); ?>)</code>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">Select which post types can be served as Markdown.</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Tab: Frontmatter
	 * ----------------------------------------------------------------*/

	private function render_frontmatter_tab( array $s ): void {
		$fields = [
			'fm_url'        => 'Canonical URL',
			'fm_title'      => 'Title',
			'fm_author'     => 'Author (name + URL)',
			'fm_date'       => 'Publish date',
			'fm_modified'   => 'Last modified date',
			'fm_type'       => 'Post type',
			'fm_summary'    => 'Excerpt / summary',
			'fm_categories' => 'Categories',
			'fm_tags'       => 'Tags',
			'fm_image'      => 'Featured image URL',
			'fm_published'  => 'Published status',
		];
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'serve_md_settings_group' ); ?>
			<?php $this->preserve_other_tabs( $s, 'frontmatter' ); ?>

			<h2>Frontmatter Fields</h2>
			<p>Choose which metadata fields appear in the YAML frontmatter.</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Include Fields</th>
					<td>
						<fieldset>
							<?php foreach ( $fields as $key => $label ) : ?>
								<label>
									<input type="checkbox" name="serve_md_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $s[ $key ] ?? 1 ); ?>>
									<?php echo esc_html( $label ); ?>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">Custom Static Fields</th>
					<td>
						<textarea name="serve_md_settings[custom_fields]" rows="5" cols="60" class="large-text code"
								  placeholder="license: https://creativecommons.org/licenses/by/4.0/&#10;language: en"
						><?php echo esc_textarea( $s['custom_fields'] ); ?></textarea>
						<p class="description">
							Add custom key-value pairs to every Markdown frontmatter. One per line in <code>key: value</code> format.<br>
							Example: <code>license: https://creativecommons.org/licenses/by/4.0/</code>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Post Meta Keys</th>
					<td>
						<textarea name="serve_md_settings[meta_keys]" rows="4" cols="60" class="large-text code"
								  placeholder="custom_subtitle&#10;reading_time"
						><?php echo esc_textarea( $s['meta_keys'] ); ?></textarea>
						<p class="description">
							Include values from post custom fields (meta). One meta key per line.<br>
							The meta key will be used as the YAML key, and the meta value as the YAML value.
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Tab: Exclusions
	 * ----------------------------------------------------------------*/

	private function render_exclusions_tab( array $s ): void {
		$categories = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] );
		$tags       = get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => false ] );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'serve_md_settings_group' ); ?>
			<?php $this->preserve_other_tabs( $s, 'exclusions' ); ?>

			<p>Posts belonging to any selected category or tag below will <strong>not</strong> be served as Markdown.
			   You can also disable Markdown on individual posts using the meta box in the post editor.</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Exclude Categories</th>
					<td>
						<fieldset class="serve-md-scroll-list">
							<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
								<?php foreach ( $categories as $term ) : ?>
									<label>
										<input type="checkbox" name="serve_md_settings[exclude_categories][]"
											   value="<?php echo esc_attr( $term->term_id ); ?>"
											   <?php checked( in_array( $term->term_id, $s['exclude_categories'], true ) ); ?>>
										<?php echo esc_html( $term->name ); ?>
									</label><br>
								<?php endforeach; ?>
							<?php else : ?>
								<em>No categories found.</em>
							<?php endif; ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">Exclude Tags</th>
					<td>
						<fieldset class="serve-md-scroll-list">
							<?php if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) : ?>
								<?php foreach ( $tags as $term ) : ?>
									<label>
										<input type="checkbox" name="serve_md_settings[exclude_tags][]"
											   value="<?php echo esc_attr( $term->term_id ); ?>"
											   <?php checked( in_array( $term->term_id, $s['exclude_tags'], true ) ); ?>>
										<?php echo esc_html( $term->name ); ?>
									</label><br>
								<?php endforeach; ?>
							<?php else : ?>
								<em>No tags found.</em>
							<?php endif; ?>
						</fieldset>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Tab: Crawler Log
	 * ----------------------------------------------------------------*/

	private function render_log_tab( array $s ): void {
		$logger = Serve_MD_Logger::instance();
		$stats  = $logger->get_log_stats();
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$bot    = sanitize_text_field( $_GET['bot'] ?? '' );
		$data   = $logger->get_log_entries( 30, $page, $bot );
		$pages  = (int) ceil( $data->total / 30 );

		if ( ! empty( $_GET['cleared'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Log cleared.</p></div>';
		}
		?>

		<!-- Settings form for log options -->
		<form method="post" action="options.php" style="margin-bottom:0">
			<?php settings_fields( 'serve_md_settings_group' ); ?>
			<?php $this->preserve_other_tabs( $s, 'log' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Logging</th>
					<td>
						<label>
							<input type="checkbox" name="serve_md_settings[enable_log]" value="1" <?php checked( $s['enable_log'] ); ?>>
							Enable crawler request logging
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Retention</th>
					<td>
						<input type="number" name="serve_md_settings[log_retention_days]" value="<?php echo esc_attr( $s['log_retention_days'] ); ?>" min="1" max="365" class="small-text"> days
						<p class="description">Entries older than this will be automatically pruned.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Max Entries</th>
					<td>
						<input type="number" name="serve_md_settings[log_max_entries]" value="<?php echo esc_attr( $s['log_max_entries'] ); ?>" min="0" class="small-text">
						<p class="description">Hard cap on number of log rows. Set to 0 for no limit.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Max Size (MB)</th>
					<td>
						<input type="number" name="serve_md_settings[log_max_size_mb]" value="<?php echo esc_attr( $s['log_max_size_mb'] ); ?>" min="0" class="small-text">
						<p class="description">Hard cap on log table data size in megabytes. Set to 0 for no limit.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save Log Settings' ); ?>
		</form>

		<!-- Stats summary -->
		<div class="serve-md-stats-grid">
			<div class="serve-md-stat-card">
				<span class="serve-md-stat-number"><?php echo esc_html( number_format( $stats['total'] ) ); ?></span>
				<span class="serve-md-stat-label">Total Requests</span>
			</div>
			<div class="serve-md-stat-card">
				<span class="serve-md-stat-number"><?php echo esc_html( number_format( $stats['today'] ) ); ?></span>
				<span class="serve-md-stat-label">Today</span>
			</div>
			<div class="serve-md-stat-card">
				<span class="serve-md-stat-number"><?php echo esc_html( count( $stats['bots'] ) ); ?></span>
				<span class="serve-md-stat-label">Unique Bots</span>
			</div>
		</div>

		<!-- Bot breakdown -->
		<?php if ( ! empty( $stats['bots'] ) ) : ?>
		<h3>Requests by Bot</h3>
		<table class="widefat striped serve-md-bot-table">
			<thead>
				<tr><th>Bot</th><th>Requests</th><th></th></tr>
			</thead>
			<tbody>
				<?php foreach ( $stats['bots'] as $b ) : ?>
					<tr>
						<td><?php echo esc_html( $b['bot_name'] ?: 'Unknown' ); ?></td>
						<td><?php echo esc_html( number_format( $b['cnt'] ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( [ 'page' => $this->page_slug, 'tab' => 'log', 'bot' => $b['bot_name'] ], admin_url( 'options-general.php' ) ) ); ?>">
								Filter &rarr;
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<!-- Request log table -->
		<h3>
			Recent Requests
			<?php if ( $bot ) : ?>
				&mdash; filtered by <strong><?php echo esc_html( $bot ); ?></strong>
				(<a href="<?php echo esc_url( add_query_arg( [ 'page' => $this->page_slug, 'tab' => 'log' ], admin_url( 'options-general.php' ) ) ); ?>">clear filter</a>)
			<?php endif; ?>
		</h3>

		<?php if ( empty( $data->rows ) ) : ?>
			<p><em>No log entries yet.</em></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Time</th>
						<th>URL</th>
						<th>Post</th>
						<th>Bot</th>
						<th>Method</th>
						<th>IP</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data->rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><code style="word-break:break-all"><?php echo esc_html( $row->url ); ?></code></td>
							<td>
								<?php if ( $row->post_id ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $row->post_id ) ); ?>">
										#<?php echo esc_html( $row->post_id ); ?>
									</a>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row->bot_name ); ?></td>
							<td><code><?php echo esc_html( $row->method ); ?></code></td>
							<td><code><?php echo esc_html( $row->ip_address ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						$base_url = add_query_arg( [ 'page' => $this->page_slug, 'tab' => 'log', 'bot' => $bot ], admin_url( 'options-general.php' ) );
						echo paginate_links( [
							'base'    => $base_url . '%_%',
							'format'  => '&paged=%#%',
							'current' => $page,
							'total'   => $pages,
						] );
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Clear log -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
			<?php wp_nonce_field( 'serve_md_clear_log' ); ?>
			<input type="hidden" name="action" value="serve_md_clear_log">
			<?php submit_button( 'Clear Entire Log', 'delete', 'submit', false, [
				'onclick' => "return confirm('Are you sure you want to delete all log entries?');",
			] ); ?>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	private function get_tab_url( string $tab ): string {
		return add_query_arg( [
			'page' => $this->page_slug,
			'tab'  => $tab,
		], admin_url( 'options-general.php' ) );
	}

	/**
	 * When saving one tab, we need to preserve settings from other tabs
	 * that aren't present in the current form. Output them as hidden fields.
	 */
	private function preserve_other_tabs( array $s, string $current_tab ): void {
		$general_keys     = [ 'enable_content_negotiation', 'enable_md_url', 'enable_discovery_link', 'post_types' ];
		$frontmatter_keys = [ 'fm_url', 'fm_title', 'fm_author', 'fm_date', 'fm_modified', 'fm_type', 'fm_summary', 'fm_categories', 'fm_tags', 'fm_image', 'fm_published', 'custom_fields', 'meta_keys' ];
		$exclusion_keys   = [ 'exclude_categories', 'exclude_tags' ];
		$log_keys         = [ 'enable_log', 'log_retention_days', 'log_max_entries', 'log_max_size_mb' ];

		$preserve = [];
		if ( $current_tab !== 'general' )     $preserve = array_merge( $preserve, $general_keys );
		if ( $current_tab !== 'frontmatter' ) $preserve = array_merge( $preserve, $frontmatter_keys );
		if ( $current_tab !== 'exclusions' )  $preserve = array_merge( $preserve, $exclusion_keys );
		if ( $current_tab !== 'log' )         $preserve = array_merge( $preserve, $log_keys );

		foreach ( $preserve as $key ) {
			$value = $s[ $key ] ?? '';
			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					printf(
						'<input type="hidden" name="serve_md_settings[%s][]" value="%s">',
						esc_attr( $key ),
						esc_attr( $v )
					);
				}
			} else {
				printf(
					'<input type="hidden" name="serve_md_settings[%s]" value="%s">',
					esc_attr( $key ),
					esc_attr( $value )
				);
			}
		}
	}
}
