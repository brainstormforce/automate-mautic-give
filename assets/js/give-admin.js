jQuery(document).ready(function( $ ) {
	var jq = jQuery.noConflict();
	jq( ".amp-give-forms" ).select2();;

	jq(document).on( "click", "#send-give-donors", function() {

		jq( '.apm-wp-spinner' ).css( "visibility", "visible" );

		var data= {
			action:'import_apm_give_donors'
		};

		jq.post( ajaxurl, data, function(response) {
			// cosole.log(response);
			jq( '.apm-wp-spinner' ).removeClass('spinner');
			jq( '.apm-wp-spinner' ).addClass('dashicons dashicons-yes');
		});
		
	});

});