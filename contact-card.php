<?php

/**
 * The Contact Card Plugin
 * 
 * @package Contact Card
 * @subpackage Main
 *
 * @todo Use https://github.com/jeroendesloovere/vcard for vCard download implementation
 */

/**
 * Plugin Name:       Contact Card
 * Description:       Display contact cards of your site's users.
 * Plugin URI:        https://github.com/lmoffereins/contact-card/
 * Version:           0.1.0
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins/
 * Text Domain:       contact-card
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/contact-card
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Contact_Card' ) ) :
/**
 * The main plugin class
 *
 * @since 0.1.0
 */
final class Contact_Card {

	/**
	 * Setup and return the singleton pattern
	 *
	 * @since 0.1.0
	 *
	 * @uses Contact_Card::setup_globals()
	 * @uses Contact_Card::setup_actions()
	 * @return The single Contact_Card
	 */
	public static function instance() {

		// Store instance locally
		static $instance = null;

		if ( null === $instance ) {
			$instance = new Contact_Card;
			$instance->setup_globals();
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Prevent the plugin class from being loaded more than once
	 */
	private function __construct() { /* Nothing to do */ }

	/** Private methods *************************************************/

	/**
	 * Setup default class globals
	 *
	 * @since 0.1.0
	 */
	private function setup_globals() {

		/** Versions **********************************************************/

		$this->version      = '0.1.0';

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url ( $this->file );

		// Includes
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes' );
		$this->includes_url = trailingslashit( $this->plugin_url . 'includes' );

		// Languages
		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );

		/** Misc **************************************************************/

		$this->extend       = new stdClass();
		$this->domain       = 'contact-card';
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 0.1.0
	 */
	private function setup_actions() {

		// Load textdomain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Register shortcode
		add_action( 'init', array( $this, 'register_shortcode' ) );

		// Custom Fields
		add_action( 'contact_card_attribute_field_user_select',   array( $this, 'field_user_select'   ), 10, 3 );
		add_action( 'wp_ajax_contact_card_suggest_user',          array( $this, 'suggest_user'        )        );
		add_action( 'contact_card_attribute_field_checkbox_text', array( $this, 'field_checkbox_text' ), 10, 3 );
	}

	/** Plugin **********************************************************/

	/**
	 * Load the translation file for current language. Checks the languages
	 * folder inside the plugin first, and then the default WordPress
	 * languages folder.
	 *
	 * Note that custom translation files inside the plugin folder will be
	 * removed on plugin updates. If you're creating custom translation
	 * files, please use the global language folder.
	 *
	 * @since 0.1.0
	 *
	 * @uses apply_filters() Calls 'plugin_locale' with {@link get_locale()} value
	 * @uses load_textdomain() To load the textdomain
	 * @uses load_plugin_textdomain() To load the textdomain
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/contact-card/' . $mofile;

		// Look in global /wp-content/languages/contact-card folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/contact-card/languages/ folder
		load_textdomain( $this->domain, $mofile_local );

		// Look in global /wp-content/languages/plugins/
		load_plugin_textdomain( $this->domain );
	}

	/** Public methods **************************************************/

	/**
	 * Register the [contact] shortcode
	 *
	 * @since 0.1.0
	 *
	 * @uses add_shortcode()
	 * @uses wp_regsiter_style()
	 * @uses apply_filters() Calls 'contact_card_use_shortcacke'
	 * @uses Contact_Card::get_attributes()
	 * @uses shortcode_ui_register_for_shortcode()
	 * @uses apply_filters() Calls 'contact_card_shortcake_args'
	 */
	public function register_shortcode() {

		// Register shortcode
		add_shortcode( 'contact', array( $this, 'the_shortcode' ) );

		// Register style
		wp_register_style( 'contact-card', $this->includes_url . 'css/contact-card.css' );

		// Register for Shortcake
		if ( class_exists( 'Shortcode_UI' ) && apply_filters( 'contact_card_use_shortcake', false ) ) {

			// Include User Select Field
			include( $this->includes_dir . 'class-shortcode-ui-field-user-select.php' );

			// Prepare attributes
			$attributes = $this->_get_attributes();
			foreach ( array_keys( $attributes ) as $attr ) {
				$attributes[ $attr ]['attr'] = $attr;
			}

			shortcode_ui_register_for_shortcode( 'contact', apply_filters( 'contact_card_shortcake_args', array(
				'label'         => __( 'Contact Card', 'contact-card' ),
				'listItemImage' => 'dashicons-id',
				'attrs'         => $attributes
			) ) );

			// Add our styles to the WP editor
			add_filter( 'editor_stylesheets', function( $stylesheets ) {
				$stylesheets[] = $this->includes_url . 'css/contact-card.css';
				return $stylesheets;
			} );

		// Register custom shortcode implementation
		} else {

			// Setup insert card modal
			add_action( 'media_buttons',     array( $this, 'insert_card_button'  ), 50 );
			add_action( 'wp_tiny_mce_init',  array( $this, 'insert_card_modal'   )     );
			add_filter( 'wp_enqueue_editor', array( $this, 'insert_card_enqueue' )     );
		}
	}

	/**
	 * Enqueue the shortcode styles
	 *
	 * @since 0.1.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'contact-card' );
	}

	/**
	 * Render the shortcode
	 *
	 * @since 0.1.0
	 *
	 * @uses Contact_Card::get_attributes()
	 * @uses Contact_Card::normalize_empty_attributes()
	 * @uses shortcode_atts()
	 * @uses get_user_by() To get the card's user
	 * @uses Contact_Card::get_card()
	 *
	 * @param array $args This shortcode's attributes
	 * @return string|void The shortcode content or nothing
	 */
	public function the_shortcode( $args = array() ) {

		// Define shortcode defaults
		$defaults = wp_list_pluck( $this->_get_attributes(), 'default' );

		// Parse shortcode args
		$args = $this->normalize_empty_attributes( $args );
		$args = shortcode_atts( $defaults, $args, 'contact' );

		// Find selected user
		if ( ! empty( $args['user'] ) ) {
			$user = get_user_by( is_numeric( $args['user'] ) ? 'id' : 'login', $args['user'] );
		} else {
			$user = false;
		}

		// Bail when no user was found
		if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		// Let's build the card
		$content = $this->get_card( $user, $args );

		// Enqueue card style
		if ( ! empty( $content ) ) {
			$this->enqueue_styles();
		}

		return $content;
	}

	/**
	 * Returns a flat array of the attributes
	 *
	 * @since 0.1.0
	 *
	 * @uses Contact_Card::get_attributes()
	 * @return array Attributes flattened
	 */
	public function _get_attributes() {
		$_atts = array();
		foreach ( $this->get_attributes() as $atts ) {
			$_atts = array_merge( $_atts, $atts );
		}
		return $_atts;
	}

	/**
	 * Return the shortcode attributes with their details
	 *
	 * @since 0.1.0
	 *
	 * @uses apply_filters() Calls 'contact_card_attributes'
	 * @return array Shortcode attributes
	 */
	public function get_attributes() {
		$attributes = apply_filters( 'contact_card_attributes', array(

			/** User ********************************************/

			// User section
			'user' => array(

				// User
				'user' => array(
					'label'       => __( 'Displayed User', 'contact-card' ),
					'type'        => 'user_select', // Custom input type
					'query'       => array( 
						'per_page' => 15 
					),
					'placeholder' => __( 'Search User&hellip;', 'contact-card' ),
					'required'    => true
				),

				// User Subtitle
				// 'subtitle' => array(
				// 	'label'   => __( 'User Subtitle', 'contact-card' ),
				// 	'type'    => 'text',
				// 	'default' => ''
				// ),
			),

			/** Markup ******************************************/

			// Markup section
			'markup' => array(

				// Link to User
				// 'link' => array(
				// 	'label'   => __( 'Link to User', 'contact-card' ),
				// 	'type'    => 'checkbox',
				// 	'default' => false
				// ),

				// Avatar Size
				'avatar_size' => array(
					'label'   => __( 'Avatar Size', 'contact-card' ),
					'type'    => 'number',
					'default' => 80
				),

				// Avatar Alignment
				'avatar_align' => array(
					'label'   => __( 'Align Avatar', 'contact-card' ),
					'type'    => 'select',
					'options' => array(
						'left'  => __( 'Left', 'contact-card' ),
						'right' => __( 'Right', 'contact-card' ),
					),
					'default' => 'left'
				),

				// Display detail labels
				'labels' => array(
					'label'       => __( 'Detail Labels', 'contact-card' ),
					'description' => __( 'Enable to display details with labels', 'contact-card' ),
					'type'        => 'checkbox',
					'default'     => false
				),

			),

			/** Details *****************************************/

			// Details section
			'details' => array(

				// Email
				'email' => array(
					'label' => __( 'Email', 'contact-card' ),
					'type'  => 'text',
				),

				// Phone
				'tel' => array(
					'label' => __( 'Tel', 'contact-card' ),
					'type'  => 'text',
				),
			),
		) );

		// Parse args with defaults
		foreach ( $attributes as $section => $atts ) {
			foreach ( $atts as $attr => $args ) {
				$attributes[ $section ][ $attr ] = wp_parse_args( $args, array(
					'label'   => ucfirst( $attr ),
					'type'    => 'text',
					'default' => false,
				) );
			}
		}

		return $attributes;
	}

	/**
	 * Return the attribute sections
	 *
	 * @since 0.1.0
	 *
	 * @uses apply_filters() Calls 'contact_card_attribute_sections'
	 * @return array Attribute sections
	 */
	public function get_attribute_sections() {
		return (array) apply_filters( 'contact_card_attribute_sections', array(
			'user'    => __( 'User',        'contact-card' ),
			'markup'  => __( 'Card Markup', 'contact-card' ),
			'details' => __( 'Details',     'contact-card' ),
		) );
	}

	/**
	 * Normalize shortcode attributes for empty ones
	 *
	 * @since 0.1.0
	 *
	 * @param array $args Shortcode attributes
	 * @return array Normalized attributes
	 */
	public function normalize_empty_attributes( $args ) {
		foreach ( (array) $args as $key => $value ) {
			if ( is_int( $key ) ) {
				$args[ strtolower( $value ) ] = true;
				unset( $args[ $key ] );
			}
		}
		return $args;
	}

	/**
	 * Return the markup of the user's contact card
	 *
	 * @since 0.1.0
	 *
	 * @uses Contact_Card::get_user_url()
	 * @uses Contact_Card::get_user_details()
	 * @uses apply_filters() Calls 'contact_card_card_styles'
	 * @uses apply_filters() Calls 'contact_card_before_details'
	 * @uses apply_filters() Calls 'contact_card_after_details'
	 *
	 * @param WP_User $user User object
	 * @param array $args Shortcode arguments
	 * @return string Contact card markup
	 */
	private function get_card( $user, $args ) {

		// Define card id
		$card_id = "contact-card-{$user->ID}";

		// Define classes
		$class = ( 'right' !== $args['avatar_align'] ) ? array( 'avatar-left' ) : array();
		$class = array_map( 'sanitize_html_class', array_unique( $class ) );

		// Define user data
		$user_url = ! empty( $args['link'] ) ? $this->get_user_url( $user->ID ) : false;
		$details  = $this->get_user_details( $user, $args );

		// Start an output buffer
		ob_start(); ?>

		<div id="<?php echo $card_id; ?>" class="contact-card vcard <?php echo implode( ' ', $class ); ?>">
			<div class="contact-body">
				<header class="contact-header">
					<div class="contact-photo">
						<?php printf ( $user_url ? '<a href="%2$s">%1$s</a>' : '%s',
							get_avatar( $user, (int) $args['avatar_size'] ),
							esc_url( $user_url )
						); ?>
					</div>

					<h4 class="contact-title">
						<?php printf ( $user_url ? '<a href="%2$s">%1$s</a>' : '%s',
							esc_html( $user->display_name ),
							esc_url( $user_url )
						); ?>
					</h4>

					<?php if ( ! empty( $args['subtitle'] ) ) : ?>
					<span class="contact-sub-title"><?php echo esc_html( $args['subtitle'] ); ?></span>
					<?php endif; ?>
				</header>

				<?php 

				/**
				 * Display content in the card before the user's details
				 *
				 * @since 0.1.0
				 * 
				 * @param WP_User $user    The current user's object
				 * @param array   $args    The shortcode filtered attributes
				 * @param array   $details The collection of user details
				 */
				do_action( 'contact_card_before_details', $user, $args, $details ); ?>

				<div class="contact-details">
					<dl>
						<?php foreach ( $details as $name => $detail ) : ?>
						<span class="detail <?php echo esc_attr( $name ); ?>">
							<dt <?php if ( ! $args['labels'] ) { echo 'class="screen-reader-text"'; } ?>><?php echo esc_html( $detail['label'] ); ?></dt>
							<dd><?php echo $detail['value']; ?></dd>
						</span>
						<?php endforeach; ?>
					</dl>
				</div>

				<?php 

				/**
				 * Display content in the card after the user's details
				 *
				 * @since 0.1.0
				 * 
				 * @param WP_User $user    The current user's object
				 * @param array   $args    The shortcode filtered attributes
				 * @param array   $details The collection of user details
				 */
				do_action( 'contact_card_after_details', $user, $args, $details ); ?>
			</div>

			<?php 

			/**
			 * Filter the user's contact card's custom styles
			 *
			 * @since 0.1.0
			 *
			 * @param  array   $styles Custom card styles
			 * @param  WP_User $user   The current user's object
			 * @param  array   $args   The shortcode filtered attributes
			 * @return array           Custom card styles
			 */
			if ( $styles = (array) apply_filters( 'contact_card_card_styles', array(), $user, $args ) ) : ?>
			<style>
				<?php echo implode( "\n", $styles ); ?>
			</style>
			<?php endif; ?>
		</div>

		<?php

		// Get the output buffer contents
		$card = ob_get_clean();

		return $card;
	}

	/**
	 * Return the user's contact card details
	 *
	 * @since 0.1.0
	 *
	 * @uses is_email()
	 * @uses apply_filters() Calls 'contact_card_details'
	 *
	 * @param WP_User $user User object
	 * @param array $args Shortcode attributes
	 * @return array User details
	 */
	public function get_user_details( $user, $args ) {

		// Define local variable
		$details = array();

		// Define email detail
		if ( $args['email'] ) {

			// Consider custom provided email
			$email = is_email( $args['email'] ) ? $args['email'] : $user->user_email;
			$details['email'] = array( 
				'label' => __( 'Email', 'contact-card' ), 
				'value' => '<a href="mailto:' . $email . '">' . $email . '</a>'
			);
		}

		// Define tel detail
		if ( $args['tel'] ) {

			// Strip all non-numeric chars
			$value = preg_replace( '/\D/', '', trim( $args['tel'] ) );

			// Strip leading 31
			if ( '31' === substr( $value, 0, 2 ) ) {
				$value = substr( $value, 2 );

			// Strip leading 0
			} elseif ( '0' === substr( $value, 0, 1 ) ) {
				$value = substr( $value, 1 );
			}

			$details['tel'] = array(
				'label' => __( 'Phone', 'contact-card' ),
				'value' => '<a href="tel:+31' . $value . '">' . esc_html( $args['tel'] ) . '</a>'
			);
		}

		/**
		 * Filter the user's contact card details
		 *
		 * @since 0.1.0
		 * 
		 * @param array   $details The collection of user details
		 * @param WP_User $user    The current user's object
		 * @param array   $args    The shortcode filtered attributes
		 */
		return (array) apply_filters( 'contact_card_details', $details, $user, $args );
	}

	/**
	 * Return the url of a user's location
	 *
	 * @since 0.1.0
	 *
	 * @uses bp_core_get_user_domain()
	 * @uses bbp_get_user_profile_url()
	 * @uses get_author_posts_url()
	 * @uses apply_filters() Calls 'contact_card_url'
	 *
	 * @param int $user_id User ID
	 * @return string|bool User url or False if none found
	 */
	public function get_user_url( $user_id ) {

		// Support BuddyPress
		if ( function_exists( 'buddypress' ) ) {
			$url = bp_core_get_user_domain( $user_id );

		// Support bbPress
		} elseif ( function_exists( 'bbpress' ) ) {
			$url = bbp_get_user_profile_url( $user_id );

		// Default to author posts url
		} else {
			$url = get_author_posts_url( $user_id );
		}

		/**
		 * Filter the user's contact card details
		 *
		 * Return False to remove the link from the card.
		 *
		 * @since 0.1.0
		 * 
		 * @param array $url  The card's user url
		 * @param int   $user User ID
		 */
		return apply_filters( 'contact_card_url', $url, $user_id );
	}

	/** Editor **********************************************************/

	/**
	 * Display a button to insert a contact card
	 *
	 * @since 0.1.0
	 *
	 * @param string $editor_id Editor id
	 */
	public function insert_card_button( $editor_id ) { ?>

		<a type="button" href="#TB_inline?width=640&amp;height=480&amp;inlineId=contact-card-modal-wrap" class="insert-contact-card button" title="<?php esc_html_e( 'Insert Contact Card', 'contact-card' ); ?>">
			<span class="wp-media-buttons-icon dashicons-before dashicons-id"></span>
		</a>

		<?php
	}

	/**
	 * Hook the method to display the insert a contact card window
	 *
	 * @since 0.1.0
	 */
	public function insert_card_modal() {

		// Bail when we've been here before
		if ( ! did_action( current_action() ) === 1 )
			return;

		// Output window after tiny mce script
		add_action( 'admin_print_footer_scripts', array( $this, 'insert_card_print_modal' ), 51 );
		add_action( 'wp_print_footer_scripts',    array( $this, 'insert_card_print_modal' ), 51 );
	}

	/**
	 * Output the modal to insert a contact card
	 *
	 * @since 0.1.0
	 *
	 * @uses Contact_Card::get_attributes()
	 * @uses Contact_Card::insert_card_attributes_field()
	 */
	public function insert_card_print_modal() {

		// Define local variable(s)
		$sections   = $this->get_attribute_sections();
		$attributes = $this->get_attributes(); 

		?>

		<div id="contact-card-modal-wrap" style="display:none;">
			<div id="contact-card-modal">
				<form>
					<header class="modal-header">
						<span class="modal-title"><?php _e( 'Insert a Contact Card', 'contact-card' ); ?></span>
					</header>

					<div class="modal-description">
						<p><?php _e( "Use this shortcode generator to insert a user's card with contact details into your post content.", 'contact-card' ); ?></p>
					</div>

					<fieldset>
						<?php foreach ( $sections as $section => $section_title ) : 
							// Skip section when it is empty
							if ( empty( $attributes[ $section ] ) )
								continue;
						?>
						<section class="contact-card-section section-<?php echo esc_attr( $section ); ?>">
							<header class="section-header">
								<span><?php echo esc_html( $section_title ); ?></span>
							</header>

							<?php foreach ( $attributes[ $section ] as $attr => $args ) :

								// Define local variable(s)
								$_attr = esc_attr( $attr );
								$el_id = "contact-card-{$_attr}";

							?>
							<div class="contact-card-attribute attribute-<?php echo $_attr; ?> field-<?php echo $args['type']; ?>">

								<div class="label">
									<label for="<?php echo $el_id; ?>"><?php echo esc_html( $args['label'] ); ?></label>
								</div>
								<div class="field">
									<?php $this->insert_card_attribute_field( $attr, $args, $el_id ); ?>

									<?php if ( ! empty( $args['default'] ) && true !== $args['default'] ) : ?>
									<span class="description"><?php printf( esc_html__( 'Default value is %s', 'contact-card' ), '<code>' . esc_html( isset( $args['options'] ) && isset( $args['options'][ $args['default'] ] ) ? $args['options'][ $args['default'] ] : $args['default'] ) . '</code>' ); ?></span>
									<?php endif; ?>
								</div>

							</div>
							<?php endforeach; ?>
						</section>
						<?php endforeach; ?>

					</fieldset>

					<footer class="modal-footer">
						<input type="submit" id="contact-card-submit" class="button button-primary" value="<?php esc_html_e( 'Insert Card', 'contact-card' ); ?>" />
					</footer>
				</form>
			</div>
		</div><!-- #contact-card-modal-wrap -->

		<?php
	}

	/**
	 * Output the requested attribute's field
	 *
	 * @since 0.1.0
	 *
	 * @uses Contact_Card::html_atts()
	 * @uses do_action() Calls 'contact_card_attribute_field_{type}'
	 * @param string $attr Attribute name
	 * @param array $args Field arguments
	 * @param string $el_id Element ID
	 */
	public function insert_card_attribute_field( $attr, $args, $el_id ) {

		// Define element attributes
		$element         = array();
		$element['id']   = $el_id;
		$element['name'] = esc_attr( $attr );

		if ( isset( $args['placeholder'] ) ) {
			$element['placeholder'] = $args['placeholder'];
		}

		// Check the field type
		switch ( $args['type'] ) {

			// Input
			case 'checkbox' :
			case 'date' : 
			case 'email' : 
			case 'number' :
			case 'radio' :
			case 'text' :
			case 'url' : 

				// Handle input options
				if ( isset( $args['options'] ) && ! empty( $args['options'] ) ) : ?>

		<div class="options">
			<?php foreach ( $args['options'] as $val => $option ) : 
				$_element          = $element;
				$_element['id']   .= '_' . esc_attr( $val );
				$_element['name'] .= '[]';
				$_element['value'] = $val;

				// Check defaults
				if ( in_array( $val, (array) $args['default'] ) ) {
					if ( 'radio' === $args['type'] ) {
						$_element['selected'] = 'selected';
					} else {
						$_element['checked'] = 'checked';
					}
				} ?>

			<label for="<?php echo $element['id']; ?>"><?php echo esc_html( $option ); ?></label>
			<input type="<?php echo $args['type']; ?>" <?php $this->html_atts( $_element ); ?>/>

			<?php endforeach; ?>
		</div>

			<?php else :

				// Consider checkbox
				if ( 'checkbox' === $args['type'] ) {
					$element['value'] = 1;
					if ( $args['default'] ) {
						$element['checked'] = 'checked';
					}
				} ?>

		<input type="<?php echo $args['type']; ?>" <?php $this->html_atts( $element ); ?>/>

			<?php
				endif;
				break;

			// Textarea
			case 'textarea' : ?>

		<textarea <?php $this->html_atts( $element ); ?>></textarea>

			<?php
				break;

			// Textarea
			case 'multiselect' :
				$element['name']    .= '[]';
				$element['multiple'] = 'multiple';
			case 'select' : ?>

		<select <?php $this->html_atts( $element ); ?>>
			<option value=""><?php _e( '&mdash; Select a value &mdash;', 'contact-card' ); ?></option>
			<?php foreach ( $args['options'] as $val => $option ) : ?>
			<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $option ); ?></option>
			<?php endforeach; ?>
		</select>

			<?php
				break;

			// Default
			default :
				/**
				 * Output the shortcode attribute's input field
				 *
				 * The $attr part of the action name refers to the current 
				 * attribute's field type.
				 *
				 * @since 0.1.0
				 * 
				 * @param string $attr    Attribute name
				 * @param array  $args    Field arguments
				 * @param array  $element Element attributes
				 */
				do_action( "contact_card_attribute_field_{$args['type']}", $attr, $args, $element );
		} // switch
	}

	/**
	 * Helper function to print HTML element attributes
	 *
	 * @since 0.1.0
	 *
	 * @param array $atts Element attributes as key => value
	 */
	public function html_atts( $atts ) {
		foreach ( $atts as $k => $v ) {
			printf( ' %s="%s"', $k, esc_attr( $v ) );
		}
	}

	/**
	 * Enqueue scripts to insert a contact card in the editor
	 *
	 * @since 0.1.0
	 */
	public function insert_card_enqueue() {
		wp_enqueue_style( 'contact-card-editor', $this->includes_url . 'css/contact-card-editor.css', array(), $this->version );
		wp_enqueue_script( 'contact-card-editor', $this->includes_url . 'js/contact-card-editor.js', array( 'jquery', 'thickbox' ), $this->version );
	}

	/**
	 * Output the field of the `user_select` type
	 *
	 * @since 0.1.0
	 *
	 * @param string $array Attribute name
	 * @param array $args Field arguments
	 * @param array $element Element attributes
	 */
	public function field_user_select( $attr, $args, $element ) { ?>

		<input type="text" <?php $this->html_atts( $element ); ?> class="user-select" data-ajax-url="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'contact_card_suggest_user' ), admin_url( 'admin-ajax.php', 'relative' ) ), 'contact_card_suggest_user_nonce' ) ); ?>" />

		<?php
	}

	/**
	 * Output the field of the `checkbox_text` type
	 *
	 * @since 0.1.0
	 *
	 * @param string $attr Attribute name
	 * @param array $args Field arguments
	 * @param array $element Element attributes
	 */
	public function field_checkbox_text( $attr, $args, $element ) { 

		// Define element attributes
		$element1 = $element2 = $element;
		$element2['id'] .= '_text';

		?>

		<input type="checkbox" value="1" <?php $this->html_atts( $element1 ); ?>/>
		<input type="text" <?php $this->html_atts( $element2 ); ?>/>

		<?php
	}

	/**
	 * Ajax action for facilitating the contact user auto-suggest
	 *
	 * @since 0.1.0
	 *
	 * @uses check_ajax_referer()
	 * @uses WP_User_Query
	 * @uses WP_User
	 */
	public function suggest_user() {
		global $wpdb;

		// Bail early if no request
		if ( empty( $_REQUEST['q'] ) ) {
			wp_die( '0' );
		}

		// Bail if user cannot create posts - only authors can add shortcodes
		// NOTE: Suggest.js does not allow for sending the post ID along with the 
		// request to check for post type specific cap checking.
		if ( ! current_user_can( 'create_posts' ) ) {
			wp_die( '0' );
		}

		// Check the ajax nonce
		check_ajax_referer( 'contact_card_suggest_user_nonce' );

		// Try to get some users
		$users_query = new WP_User_Query( array(
			'search'         => '*' . $wpdb->esc_like( $_REQUEST['q'] ) . '*',
			'fields'         => array( 'ID' ),
			'search_columns' => array( 'ID', 'user_nicename', 'user_login', 'user_email' ),
			'orderby'        => 'user_login'
		) );

		// If we found some users, loop through and display them
		if ( ! empty( $users_query->results ) ) {
			foreach ( (array) $users_query->results as $user ) {
				$user = new WP_User( $user->ID );

				// Build selectable line for output
				$line = sprintf( '%s (%s)', $user->user_login, $user->ID );
				if ( $user->user_login !== $user->display_name ) {
					$line .= sprintf( ' - %s', $user->display_name );
				}

				echo $line . "\n";
			}
		}
		die();
	}
}

/**
 * Return single instance of this main plugin class
 *
 * @since 0.1.0
 * 
 * @return Contact_Card
 */
function contact_card() {
	return Contact_Card::instance();
}

// Initiate
contact_card();

endif; // class_exists
