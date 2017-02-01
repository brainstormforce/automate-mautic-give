jQuery(document).ready(function( $ ) {
	var jq = jQuery.noConflict();
	jq( ".amp-give-forms" ).select2();;

	jq(document).on( "click", "#send-give-donors", function() {

	    jq( '.amp_footer_spinner' ).removeClass('dashicons dashicons-yes');
        jq( '.amp_footer_spinner' ).addClass('spinner');
		jq( '.apm-wp-spinner' ).css( "visibility", "visible" );

		var data= {
			action:'import_apm_give_donors'
		};

		jq.post( ajaxurl, data, function(response) {
			
			jq( '.amp_footer_spinner' ).removeClass('spinner');
			jq( '.amp_footer_spinner' ).addClass('dashicons dashicons-yes');
		});
	});
});