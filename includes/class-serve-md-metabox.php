<?php
/**
 * Per-post meta box for opting out of Markdown serving
 * and for adding a "Preview Markdown" button.
 */

if ( ! defined( 'WPINC' ) ) {
	exit;
}

final class Serve_MD_Metabox {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'add_markdown_row_action' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'add_markdown_row_action' ], 10, 2 );
	}

	/**
	 * Register the meta box on all enabled post types.
	 */
	public function register_meta_box(): void {
		$post_types = Serve_MD_Core::enabled_post_types();

		foreach ( $post_types as $pt ) {
			add_meta_box(
				'serve_md_options',
				'Serve Markdown',
				[ $this, 'render_meta_box' ],
				$pt,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box.
	 */
	public function render_meta_box( WP_Post $post ): void {
		$disabled = (bool) get_post_meta( $post->ID, '_serve_md_disabled', true );
		$md_url   = Serve_MD_Core::get_markdown_url( $post );

		wp_nonce_field( 'serve_md_metabox', 'serve_md_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="serve_md_disabled" value="1" <?php checked( $disabled ); ?>>
				Disable Markdown for this post
			</label>
		</p>
		<p class="description">
			When checked, this post will not be served as Markdown via <code>.md</code> URL or content negotiation.
		</p>
		<?php if ( $post->post_status === 'publish' ) : ?>
			<p style="margin-top:12px">
				<a href="<?php echo esc_url( $md_url ); ?>" target="_blank" class="button button-secondary">
					Preview Markdown &#8599;
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save the meta box value.
	 */
	public function save_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['serve_md_nonce'] ) || ! wp_verify_nonce( $_POST['serve_md_nonce'], 'serve_md_metabox' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST['serve_md_disabled'] ) ) {
			update_post_meta( $post_id, '_serve_md_disabled', '1' );
		} else {
			delete_post_meta( $post_id, '_serve_md_disabled' );
		}
	}

	/**
	 * Add a "View Markdown" link in the post list row actions.
	 */
	public function add_markdown_row_action( array $actions, WP_Post $post ): array {
		$enabled_types = Serve_MD_Core::enabled_post_types();

		if ( ! in_array( $post->post_type, $enabled_types, true ) ) {
			return $actions;
		}

		if ( $post->post_status !== 'publish' ) {
			return $actions;
		}

		$disabled = (bool) get_post_meta( $post->ID, '_serve_md_disabled', true );
		if ( $disabled ) {
			return $actions;
		}

		$md_url = Serve_MD_Core::get_markdown_url( $post );
		$actions['serve_md_preview'] = sprintf(
			'<a href="%s" target="_blank">View Markdown</a>',
			esc_url( $md_url )
		);

		return $actions;
	}
}
