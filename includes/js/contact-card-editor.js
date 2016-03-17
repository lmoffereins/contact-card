/**
 * Contact Card Editor script
 *
 * @package Contact Card
 * @subpackage Editor
 */
jQuery(document).ready( function( $ ) {
	var $popup = $( '#contact-card-modal' ),
	    $userfield = $popup.find( '#contact-card-user' ),
	    attr = {}, shortcode;

	/**
	 * Initialize thickbox
	 */
	$( '.button.insert-contact-card' ).on( 'click', function( e ) {
		tb_click.call(this);

		$( 'body' ).addClass( 'contact-card-modal' );
		$( '#TB_closeWindowButton' ).focus();
		$( '#TB_ajaxContent' ).removeAttr( 'style' ); // Discard width/height values set in thickbox.js

		return false;
	});

	$( window ).on( 'tb_unload', function() { // Triggers twice (?)
		$( 'body' ).removeClass( 'contact-card-modal' );
		clearForm( '#contact-card-modal form' );
	});

	/**
	 * Enable $.suggest for the user input field
	 */
	$userfield.suggest(
		$userfield.data( 'ajax-url' ),
		{
			// Define custom result container class
			resultsClass: 'ac_results contact-card-user-suggest',
			onSelect: function() {
				var value = this.value;
				$userfield.val( value.substr( 0, value.indexOf( ' (' ) ) );
			}
		}
	);

	/**
	 * Insert shortcode on submit
	 */
	$popup.on( 'submit', 'form', function( e ) {
		var $this = $(this);

		// Collect form values as shortcode attributes
		$.each( $this.serializeArray(), function( i, field ) {
			// Only collect when a value is not empty
			if ( !! field.value ) {
				attr[ field.name ] = field.value;
			}
		});

		// TODO: Check required fields

		var j = Object.keys( attr ).map( function( k, i ) {
			return k + '="' + attr[ k ] + '"';
		});

		// Build shortcode with attributes
		shortcode = '[contact ' + j.join( ' ' ) + ']';

		// Insert shortcode in the editor
		window.send_to_editor( shortcode );

		// Reset the form elements
		clearForm( this );

		return false;
	});

	/**
	 * Clear the fields within the given form
	 *
	 * @since 0.1.0
	 * 
	 * @param {string} el DOM element
	 */
	clearForm = function( el ) {
		var $this = $( el );

		$.each( $this.find( ':input' ), function() {
			var type = this.type, tag = this.tagName.toLowerCase();
			if ( type == 'text' || type == 'password' || tag == 'textarea' ) {
				this.value = '';
			} else if ( type == 'checkbox' || type == 'radio' ) {
				this.checked = false;
			} else if ( tag == 'select' ) {
				this.selectedIndex = -1;
			}
		});
	}
});
