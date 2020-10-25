$(document).ready(function() {
    var $container = $('.photo_list');
    $container.imagesLoaded(function(){
        $container.masonry({
            itemSelector	: '.photo',
            columnWidth		: '.photo'
        });
    });

    $('.button').click(function() {
        $(this).blur();
    });
    
    $('.categories_trigger a').click(function(e) {
        if ( $('.categories').hasClass('visible') ) {
            close_menu();
        }
        else {
            open_menu();
        }
    });
    
    $('.content_cover').click(function(e) {
        close_menu();
    });
    
    function open_menu() {
        $('.categories').addClass('visible');
        $('.categories').stop().slideDown();
        $('.content_cover').stop().fadeIn(200);
    }

    function close_menu() {
        $('.categories').removeClass('visible');
        $('.content_cover').stop().fadeOut(200);
        $('.categories').stop().slideUp();
    }

    $('a.disabled').on('click', function(e) {
        e.preventDefault();
    });

    $('.checkbox_file').on('change', function() {
        $(this).parents('.photo').toggleClass('selected');

        var amountChecked = $('.checkbox_file:checked').length;
        if (amountChecked > 0) {
            $('#zip_download').removeClass('disabled');
        } else {
            $('#zip_download').addClass('disabled');
        }
    });

    $('#zip_download').on('click', function(e) {
        if (!$(this).hasClass('disabled')) {
            var ids = [];
            $('.checkbox_file:checked').each(function () {
                ids.push($(this).val());
            });
            Cookies.set('download_started', 0, { expires: 100 });
            setTimeout(check_download_cookie, 1000);

            $('.downloading').fadeIn();

            var url = base_url+'process.php?do=download_zip&files='+ids.toString();
            $('body').append("<iframe id='modal_zip'></iframe>");
            $('#modal_zip').attr('src', url);
        }
    });

    var downloadTimeout;
    var check_download_cookie = function() {
        if (Cookies.get("download_started") == 1) {
            Cookies.set("download_started", "false", { expires: 100 });
            $('.downloading').fadeOut();
            $('#modal_zip').remove();
        } else {
            downloadTimeout = setTimeout(check_download_cookie, 1000);
        }
    };
});
