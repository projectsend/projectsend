var dataExtraction = function(node) {
	if (node.childNodes.length > 1) {
		return node.childNodes[1].innerHTML;
	} else {
		return node.innerHTML;
	}
}

/**
 * CLOSE THE ZIP DOWNLOAD MODAL
 * Solution to close the modal. Suggested by remez, based on
 * https://stackoverflow.com/questions/29532788/how-to-display-a-loading-animation-while-file-is-generated-for-download
 */
var downloadTimeout;
var check_download_cookie = function() {
    if (Cookies.get("download_started") == 1) {
        Cookies.set("download_started", "false", { expires: 100 });
        remove_modal();
    } else {
        downloadTimeout = setTimeout(check_download_cookie, 1000);
    }
};

// Close the log CSV download modal
var logdownloadTimeout;
var check_log_download_cookie = function() {
    if (Cookies.get("log_download_started") == 1) {
        Cookies.set("log_download_started", "false", { expires: 100 });
        remove_modal();
    } else {
        logdownloadTimeout = setTimeout(check_log_download_cookie, 1000);
    }
};

/**
 * Backend functions
 */
function resizeChosen() {
    $(".chosen-container").each(function() {
        $(this).attr('style', 'width: 100%');
    });
 }
 
 function prepare_sidebar() {
     var window_width = jQuery(window).width();
     if ( window_width < 769 ) {
         $('.main_menu .active .dropdown_content').hide();
         $('.main_menu li').removeClass('active');
 
         if ( !$('body').hasClass('menu_contracted') ) {
             $('body').addClass('menu_contracted');
         }
     }
 }