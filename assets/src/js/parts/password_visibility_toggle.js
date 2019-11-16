(function () {
    'use strict';

    admin.parts.passwordVisibilityToggle = function () {

        /**
         * Adapted from http://jsfiddle.net/Ngtp7/2/
         */
        $(function () {
            $(".password_toggle").each(function (index, input) {
                var $input = $(input);
                var $container	= $($input).next('.password_toggle');
                $(".password_toggler button").click(function () {
                    var change = "";
                    var icon = $(this).find('i');
                    if ($(this).hasClass('pass_toggler_show')) {
                        $(this).removeClass('pass_toggler_show').addClass('pass_toggler_hide');
                        $(icon).removeClass('glyphicon glyphicon-eye-open').addClass('glyphicon glyphicon-eye-close');
                        change = "text";
                    } else {
                        $(this).removeClass('pass_toggler_hide').addClass('pass_toggler_show');
                        $(icon).removeClass('glyphicon glyphicon-eye-close').addClass('glyphicon glyphicon-eye-open');
                        change = "password";
                    }
                    var rep = $("<input type='" + change + "' />")
                        .attr("id", $input.attr("id"))
                        .attr("name", $input.attr("name"))
                        .attr('class', $input.attr('class'))
                        .attr('maxlength', $input.attr('maxlength'))
                        .val($input.val())
                        .insertBefore($input);
                    $input.remove();
                    $input = rep;
                }).insertBefore($container);
            });
            return false;
        });
    };
})();