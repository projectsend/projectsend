(function () {
    'use strict';

    admin.parts.main = function () {

        $(document).ready(function() {
            $(document).ready(function() {
                $('input:first').focus();
            });

            $('.confirm_generic').on('click', function(e) {
                if (!confirm(json_strings.translations.confirm_generic)) {
                    e.preventDefault();
                }
            });
    
            // Dismiss messages
            $('.message .close').on('click', function () {
                $(this).closest('.message').transition('fade');
            });
        
            window.resizeChosen = function() {
                $(".chosen-container").each(function() {
                    $(this).attr('style', 'width: 100%');
                });
            }
            
            window.prepare_sidebar = function() {
                var window_width = jQuery(window).width();
                if ( window_width < 769 ) {
                    $('.main_menu .active .dropdown_content').hide();
                    $('.main_menu li').removeClass('active');
            
                    if ( !$('body').hasClass('menu_contracted') ) {
                        $('body').addClass('menu_contracted');
                    }
                }
            }

            /** Main side menu */
            prepare_sidebar();

            resizeChosen();

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

            /** Used on the public link modal on both manage files and the upload results */
            $(document).on('click', '.public_link_copy', function(e) {
                var text = $(this).data('copy-text');
                copyTextToClipboard(text);
            });

            /** Common for all tables */
            $("#select_all").click(function(){
                var status = $(this).prop("checked");
                /** Uncheck all first in case you used pagination */
                $("tr td input[type=checkbox].batch_checkbox").prop("checked",false);
                $("tr:visible td input[type=checkbox].batch_checkbox").prop("checked",status);
            });

            if ( $.isFunction($.fn.footable) ) {
                $('.footable').footable().find('> tbody > tr:not(.footable-row-detail):nth-child(even)').addClass('odd');
            }
            
            // /** Pagination */
            // $(".go_to_page").on("click", "button", function() {
            //     var _page = $('.go_to_page #page_number').data('link');
            //     var _page_no = parseInt($('.go_to_page #page_number').val());
            //     if (typeof _page_no == 'number'){
            //         _page = _page.replace('_pgn_', _page_no);
            //     }
            //     window.location.href = _page;
            // });

            /** Password generator */
            var hdl = new Jen(true);
            hdl.hardening(true);

            $('.btn_generate_password').click(function(e) {
                var target = $(this).parents('.form-group').find('.attach_password_toggler');
                var min_chars = $(this).data('min');
                var max_chars = $(this).data('max');
                $(target).val( hdl.password( min_chars, max_chars ) );
            });


            /** File editor */
            if ( $.isFunction($.fn.datepicker) ) {
                $('.date-container .date-field').datepicker({
                    format			: 'dd-mm-yyyy',
                    autoclose		: true,
                    todayHighlight	: true
                });
            }

            $('.add-all').on('click', function() {
                var target = $(this).data('target');
                var selector = $('#'+target);
                $(selector).hide();
                //var selector = $(this).previous('.select_' + type);
                $(selector).find('option').each(function() {
                    $(this).prop('selected', true);
                });
                $(selector).trigger('chosen:updated');
                return false;
            });

            $('.remove-all').on('click', function() {
                var target = $(this).data('target');
                var selector = $('#'+target);
                //var selector = $(this).previous('.select_' + type);
                $(selector).find('option').each(function() {
                    $(this).prop('selected', false);
                });
                $(selector).trigger('chosen:updated');
                return false;
            });


            /** Misc */
            $('button').on('click', function() {
                $(this).blur();
            });

            /**
             * Modal: show a public file's URL
             */
            $('body').on('click', '.public_link', function(e) {
                $(document).psendmodal();
                var type = $(this).data('type');
                var title = $(this).data('title');
                var public_url = $(this).data('public-url');

                if ( type == 'group' ) {
                    var link_base = json_strings.uri.public_group + '?';
                    var note_text = json_strings.translations.public_group_note;
                }
                else if ( type == 'file' ) {
                    var link_base = json_strings.uri.public_download + '?';
                    var note_text = json_strings.translations.public_file_note;
                }

                var content = `<div class="public_link_modal">
                                    <p>`+title+`</p>
                                    <div class="form-group row">
                                        <textarea class="input-large form-control" rows="4" readonly>`+public_url+`</textarea>
                                        <button class="public_link_copy btn btn-primary" data-copy-text="`+public_url+`">
                                            <i class="fa fa-files-o" aria-hidden="true"></i> `+json_strings.translations.click_to_copy+`
                                        </button>
                                    </div>
                                    <p class="note">` + note_text + `</p>
                                </div>`;
                var title 	= json_strings.translations.public_url;
                $('.modal_title span').html(title);
                $('.modal_content').html(content);
            });
            
            // Edit file + upload form
            if ( $.isFunction($.fn.chosen) ) {
                $('.chosen-select').chosen({
                    no_results_text	: json_strings.translations.no_results,
                    width			: "100%",
                    search_contains	: true
                });
            }
        });

        /**
         * Event: Scroll
         */
        jQuery(window).scroll(function(event) {
        });

        /**
         * Event: Resize
         */
        jQuery(window).resize(function($) {
            prepare_sidebar();

            resizeChosen();
        });
    };
})();