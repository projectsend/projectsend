(function () {
    'use strict';

    admin.parts.mainMenuSidebar = function () {

        $(document).ready(function() {
            window.adjust_main_menu = function() {
                var window_width = jQuery(window).width();
                if ( window_width < 769 ) {
                    $('.main_menu .active .dropdown_content').hide();
                    $('.main_menu li').removeClass('active');
            
                    if ( !$('body').hasClass('menu_contracted') ) {
                        $('body').addClass('menu_contracted');
                    }
                }
            }

            adjust_main_menu();

            $('.main_menu > li.has_dropdown .nav_top_level').click(function(e) {
                e.preventDefault();

                var parent = $(this).parents('.has_dropdown');
                if ( $(parent).hasClass('active') ) {
                    $(parent).removeClass('active');
                    $(parent).find('.dropdown_content').stop().slideUp();
                }
                else {
                    if ( $('body').hasClass('menu_contracted') ) {
                        $('.main_menu li').removeClass('active');
                        $('.main_menu').find('.dropdown_content').stop().slideUp(100);
                    }
                    $(parent).addClass('active');
                    $(parent).find('.dropdown_content').stop().slideDown();
                }
            });

            $('.toggle_main_menu').click(function(e) {
                e.preventDefault();

                var window_width = jQuery(window).width();
                if ( window_width > 768 ) {
                    $('body').toggleClass('menu_contracted');
                    if ( $('body').hasClass('menu_contracted') ) {
                        Cookies.set("menu_contracted", 'true', { expires: 365 } );
                        $('.main_menu li').removeClass('active');
                        $('.main_menu').find('.dropdown_content').stop().hide();
                    }
                    else {
                        Cookies.set("menu_contracted", 'false', { expires: 365 } );
                        $('.current_nav').addClass('active');
                        $('.current_nav').find('.dropdown_content').stop().show();
                    }
                }
                else {
                    $('body').toggleClass('menu_hidden');
                    $('.main_menu li').removeClass('active');

                    if ( $('body').hasClass('menu_hidden') ) {
                        //Cookies.set("menu_hidden", 'true', { expires: 365 } );
                        $('.main_menu').find('.dropdown_content').stop().hide();
                    }
                    else {
                        //Cookies.set("menu_hidden", 'false', { expires: 365 } );
                    }
                }
            });
        });

        jQuery(window).resize(function($) {
            adjust_main_menu();
        });
    }
})();