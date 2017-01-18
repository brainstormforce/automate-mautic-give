jQuery(document).ready( function( $ ) {
	jQuery( "#give-email" ).focusout(function() {
		var input = jQuery(this);
		var reg = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
		var is_email = reg.test(input.val());
		if(is_email){
			var lead = jQuery(this).val();
			setTimeout( function(){
				var data= {
					action:'add_give_proctive_leads',
					email: lead
				};
				jQuery.post( amp_loc.ajax_url, data, function(selHtml) {
					console.log(selHtml);
				});
			}, 2000 );
		}
	});
});