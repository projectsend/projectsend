$(document).ready(function() {
    $('.btn_nav').click(function(e) {
        e.preventDefault();
        $('#wrapper').toggleClass('show-nav');
        $('#wrapper').toggleClass('open-nav');
    });

    $('a.disabled').on('click', function(e) {
        e.preventDefault();
    });

    $('.checkbox_file').on('change', function() {
        $(this).parents('.photo').toggleClass('selected');

        var amountChecked = $('.checkbox_file:checked').length;
        if (amountChecked > 0) {
            $('#zip_download').fadeIn();
            $('#zip_download #trigger').removeClass('disabled');
        } else {
            $('#zip_download').fadeOut();
            $('#zip_download #trigger').addClass('disabled');
        }
    });

    $('#zip_download #trigger').on('click', function(e) {
        if (!$(this).hasClass('disabled')) {
            var ids = [];
            $('.checkbox_file:checked').each(function () {
                ids.push($(this).val());
            });
            Cookies.set('download_started', 0, { expires: 100 });
            setTimeout(check_download_cookie, 1000);

            $('#trigger').hide();
            $('#indicator').show();

            var url = base_url+'process.php?do=download_zip&files='+ids.toString();
            $('body').append("<iframe class='modal_zip'></iframe>");
            $('.modal_zip').attr('src', url);
        }
    });

    var downloadTimeout;
    var check_download_cookie = function() {
        if (Cookies.get("download_started") == 1) {
            Cookies.set("download_started", "false", { expires: 100 });
            $('#indicator').hide();
            $('#trigger').show();
            $('.modal_zip').remove();
            $('#zip_download').removeClass('disabled');
        } else {
            downloadTimeout = setTimeout(check_download_cookie, 1000);
        }
    };
});
