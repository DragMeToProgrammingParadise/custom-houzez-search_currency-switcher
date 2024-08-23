jQuery(document).ajaxSuccess(function(event, xhr, settings) {
    // Get the current URL
    var currentUrl = window.location.href;
    // Check if the international part exists in the URL
    if (currentUrl.includes('international=1')) {
        // Remove the international part
        var updatedUrl = currentUrl.replace('international=1', 'switcher=');
        // Redirect to the updated URL
        window.location.href = updatedUrl;
    }
    
    
});


jQuery(document).ready(function(){
    // Hide divs when popup is opened
    jQuery('#exampleModal').on('show.bs.modal', function (e) {
        jQuery("#requestavaluationbuttonlateral, #whatsappgoldlogo").hide();
    });

    // Show divs when popup is closed
    jQuery('#exampleModal').on('hidden.bs.modal', function (e) {
        jQuery("#requestavaluationbuttonlateral, #whatsappgoldlogo").show();
    });
	
	//code to add hover-effect classs
	setInterval(function(){
		jQuery(".swiper-slide-active").find('.swiper-zoom-container').addClass('hover-effect');
	},500);
});
