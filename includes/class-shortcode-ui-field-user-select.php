<?php

/**
 * Shortcode UI Field User Select
 * 
 * @package Contact Card
 * @subpackage Shortcode UI
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shortcode_UI_Field_User_Select' ) ) :
/**
 * The Shortcode UI Field User Select class
 *
 * @since 0.1.0
 *
 * @see https://github.com/fusioneng/Shortcake/blob/master/inc/fields/class-field-post-select.php
 */
class Shortcode_UI_Field_User_Select {

	private static $instance;

	// All registered post fields.
	private $post_fields  = array();

	// Field Settings
	private $fields = array(
		'user_select' => array(
			'template' => 'shortcode-ui-field-user-select',
			'view'     => 'editAttributeFieldUserSelect',
		),
	);

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
			self::$instance->setup_actions();
		}

		return self::$instance;
	}

	private function setup_actions() {
		add_filter( 'shortcode_ui_fields', array( $this, 'filter_shortcode_ui_fields' ) );
		add_action( 'enqueue_shortcode_ui', array( $this, 'action_enqueue_shortcode_ui' ) );
		add_action( 'wp_ajax_shortcode_ui_user_field', array( $this, 'action_wp_ajax_shortcode_ui_post_field' ) );
		add_action( 'shortcode_ui_loaded_editor', array( $this, 'action_shortcode_ui_loaded_editor' ) );
	}

	public function filter_shortcode_ui_fields( $fields ) {
		return array_merge( $fields, $this->fields );
	}

	public function action_enqueue_shortcode_ui() {
		$plugin_dir = plugins_url( 'shortcode-ui' ); // Locate shortcake plugin manually

		wp_enqueue_script( 'select2', plugins_url( 'lib/select2/select2.min.js', $plugin_dir ), array( 'jquery', 'jquery-ui-sortable' ), '3.5.2' );
		wp_enqueue_style( 'select2', plugins_url( 'lib/select2/select2.css', $plugin_dir ), null, '3.5.2' );

		wp_enqueue_script( 'shortcode-ui-user-select', plugins_url( 'js/edit-attribute-field-user-select.js', __FILE__ ), array( 'shortcode-ui' ) );
		wp_localize_script( 'shortcode-ui-user-select', 'shortcodeUiUserFieldData', array(
			'nonce' => wp_create_nonce( 'shortcode_ui_field_user_select' ),
		) );
	}

	/**
	 * Output styles and templates used by user select field.
	 */
	public function action_shortcode_ui_loaded_editor() { ?>

		<style>
			.edit-shortcode-form .select2-container {
				min-width: 300px;
			}

			.edit-shortcode-form .select2-container a {
				transition: none;
				-webkit-transition: none;
			}

			.wp-admin .select2-drop {
				z-index: 160001;
			}
		</style>

		<script type="text/html" id="tmpl-shortcode-ui-field-user-select">
			<div class="field-block">
				<label for="{{ data.id }}">{{ data.label }}</label>
				<input type="text" name="{{ data.attr }}" id="{{ data.id }}" value="{{ data.value }}" class="shortcode-ui-user-select" />
			</div>
		</script>

		<?php
	}

	/**
	 * Ajax handler for select2 user field queries.
	 * Output JSON containing user data.
	 * Requires that shortcode, attr and nonce are passed.
	 * Requires that the field has been correctly registred and can be found in $this->post_fields
	 * Supports passing page number and search query string.
	 *
	 * @return null
	 */
	public function action_wp_ajax_shortcode_ui_post_field() {
		$nonce               = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : null;
		$requested_shortcode = isset( $_GET['shortcode'] ) ? sanitize_text_field( $_GET['shortcode'] ) : null;
		$requested_attr      = isset( $_GET['attr'] ) ? sanitize_text_field( $_GET['attr'] ) : null;
		$response            = array( 'users' => array(), 'found_users' => 0, 'users_per_page' => 0 );

		$shortcodes = Shortcode_UI::get_instance()->get_shortcodes();

		// Bail when nonce does not verify
		if ( ! wp_verify_nonce( $nonce, 'shortcode_ui_field_user_select' ) ) {
			wp_send_json_error( $response );
		}

		// Bail when the shortcode is not found
		if ( ! isset( $shortcodes[ $requested_shortcode ] ) ) {
			wp_send_json_error( $response );
		}

		$shortcode = $shortcodes[ $requested_shortcode ];

		// Find the attribute's query args defined by the registered shortcode
		foreach ( $shortcode['attrs'] as $attr ) {
			if ( $attr['attr'] === $requested_attr && isset( $attr['query'] ) ) {
				$query_args = $attr['query'];
			}
		}

		// Bail when there are no query args (?)
		if ( empty( $query_args ) ) {
			wp_send_json_error( $response );
		}

		// Hardcoded query args.
		$query_args['fields'] = array( 'ID', 'display_name', 'user_login' );
		$query_args['count_total'] = true;
		$query_args['search_columns'] = array( 'ID', 'user_nicename', 'user_login', 'user_email' );
		$query_args['number'] = isset( $query_args['per_page'] ) ? (int) $query_args['per_page'] : 25;
		$query_args['orderby'] = 'user_login';

		// Pagination
		if ( isset( $_GET['page'] ) && is_numeric( $_GET['page'] ) ) {
			$query_args['offset'] = $query_args['number'] * ( (int) $_GET['page'] - 1 );
		}

		// Search
		if ( ! empty( $_GET['s'] ) ) {
			$query_args['s'] = sanitize_text_field( $_GET['s'] );
		}

		if ( ! empty( $_GET['include'] ) ) {
			$include = is_array( $_GET['include'] ) ? $_GET['include'] : explode( ',', $_GET['include'] );
			$query_args['include'] = array_map( 'intval', $include );
			$query_args['orderby'] = 'include';
			$query_args['ignore_sticky_posts'] = true;
		}

		// Query users
		$query = new WP_User_Query( $query_args );

		foreach ( $query->users as $user ) {
			$response['users'][] = array(
				'id'   => $user->ID,
				'text' => sprintf( __( '%1$s - %2$s', 'shorcode-ui' ), $user->user_login, $user->display_name )
			);
		}

		$response['found_users']    = $query->total_users;
		$response['users_per_page'] = ! empty( $query->query_vars['number'] ) ? $query->query_vars['number'] : $query->total_users;

		wp_send_json_success( $response );
	}
}

// Make it go live
Shortcode_UI_Field_User_Select::get_instance();

endif; // class_exists
