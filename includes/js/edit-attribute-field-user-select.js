/**
 * Contact Card Shortcode UI script
 *
 * Handles display of User Select field
 *
 * @package Contact Card
 * @subpackage Shortcode UI
 */
( function( $ ) {
	var sui = window.Shortcode_UI,
	    userSelectCache = {}, // Cached data
	    mediaController;

	sui.views.editAttributeFieldUserSelect = sui.views.editAttributeField.extend( {

		events: {
			'change .shortcode-ui-user-select': 'inputChanged',
		},

		inputChanged: function( e ) {
			this.setValue( e.val );
			this.triggerCallbacks();
		},

		render: function() {
			var self = this,
			    defaults = { multiple: false },
			    data, ajaxData, $field;

			for ( var arg in defaults ) {
				if ( ! this.model.get( arg ) ) {
					this.model.set( arg, defaults[ arg ] );
				}
			}

			data = this.model.toJSON();
			data.id = 'shortcode-ui-' + this.model.get( 'attr' ) + '-' + this.model.cid;

			this.$el.html( this.template( data ) );

			ajaxData = {
				action    : 'shortcode_ui_user_field',
				nonce     : shortcodeUiUserFieldData.nonce,
				shortcode : this.shortcode.get( 'shortcode_tag'),
				attr      : this.model.get( 'attr' )
			};

			$field = this.$el.find( '.shortcode-ui-user-select' );

			$field.select2({
				placeholder: this.model.get( 'searchLabel' ) || 'Search',
				multiple: this.model.get( 'multiple' ),
				ajax: {
					url: ajaxurl,
					dataType: 'json',
					quietMillis: 250,
					data: function ( term, page ) {
						ajaxData.s    = term;
						ajaxData.page = page;
						return ajaxData;
					},
					results: function ( response, page ) {
						console.log( response, ajaxData );

						if ( ! response.success ) {
							return { results: {}, more: false };
						}

						// Cache data for quicker rendering later.
						userSelectCache = $.extend( userSelectCache, response.data.users );

						var more = ( page * response.data.users_per_page ) < response.data.found_users; // whether or not there are more results available
						return { results: response.data.users, more: more };
					},
					error: function( jqXHR, textStatus, errorThrown ) {
						console.log( jqXHR, textStatus, errorThrown );

						query.callback({
							hasError: true,
							jqXHR: jqXHR,
							textStatus: textStatus,
							errorThrown: errorThrown
						});
					},
				},

				/**
				 * Initialize Callback
				 * Used to set render the initial value.
				 * Has to make a request to get the title for the current ID.
				 */
				initSelection: function( element, callback ) {
					var ids, parsedData = [], cached, uncachedIds;

					// Convert stored value to array of IDs (int).
					ids = $(element)
						.val()
						.split(',')
						.map( function( str ) { return str.trim(); } )
						.map( function( str ) { return parseInt( str ); } );

					if ( ids.length < 1 ) {
						return;
					}

					// Check if there is already cached data.
					for ( var i = 0; i < ids.length; i++ ) {
						cached = _.find( userSelectCache, _.matches( { id: ids[i] } ) );
						if ( cached ) {
							parsedData.push( cached );
						}
					}

					// If not multiple - return single value if we have one.
					if ( parsedData.length && ! self.model.get( 'multiple' ) ) {
						callback( parsedData[0] );
						return;
					}

					uncachedIds = _.difference( ids, _.pluck( parsedData, 'id' ) );

					if ( ! uncachedIds.length ) {
						callback( parsedData );

					} else {
						var initAjaxData     = jQuery.extend( true, {}, ajaxData );
						initAjaxData.action  = 'shortcode_ui_user_field';
						initAjaxData.include = uncachedIds;

						$.get( ajaxurl, initAjaxData ).done( function( response ) {
							console.log( response, initAjaxData );

							if ( ! response.success ) {
								return { results: {}, more: false };
							}

							userSelectCache = $.extend( userSelectCache, response.data.users );

							// If not multi-select, expects single object, not array of objects.
							if ( ! self.model.get( 'multiple' ) ) {
								callback( response.data.users[0] );
								return;
							}

							// Append new data to cached data.
							// Sort by original order.
							parsedData = parsedData
								.concat( response.data.users )
								.sort(function (a, b) {
									if ( ids.indexOf( a.id ) > ids.indexOf( b.id ) ) return 1;
									if ( ids.indexOf( a.id ) < ids.indexOf( b.id ) ) return -1;
									return 0;
								});

							callback( parsedData );
							return;
						} );
					}
				},

			} );

			// Make multiple values sortable.
			if ( this.model.get( 'multiple' ) ) {
				$field.select2( 'container' ).find( 'ul.select2-choices' ).sortable({
	    			containment: 'parent',
	    			start:  function() { $( '.shortcode-ui-user-select' ).select2( 'onSortStart' ); },
	    			update: function() { $( '.shortcode-ui-user-select' ).select2( 'onSortEnd'   ); }
				});
			}

			return this;
		}
	} );

	/**
	 * Extending SUI Media Controller to hide Select2 UI Drop-Down when menu
	 * changes in Meida modal
	 * 1. going back/forth between different shortcakes (refresh)
	 * 2. changing the menu in left column (deactivate)
	 * 3. @TODO closing the modal.
	 */
	mediaController = sui.controllers.MediaController;
	sui.controllers.MediaController = mediaController.extend({

		refresh: function(){
			mediaController.prototype.refresh.apply( this, arguments );
			this.destroySelect2UI();
		},

		//doesn't need to call parent as it already an "abstract" method in parent to provide callback
		deactivate: function() {
			this.destroySelect2UI();
		},

		destroySelect2UI: function() {
			$( '.shortcode-ui-user-select.select2-container' ).select2( "close" );
		}
	});

} )( jQuery );
