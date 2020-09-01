(function () {
    'use strict';
    
    admin.pages.dashboard = function () {
        
        $(document).ready(function(){
            // Statistics
            function ajax_widget_statistics( days ) {
                var target = $('.statistics_graph');
                target.html('<div class="loading-graph">'+
                                '<img src="'+json_strings.uri.assets_img+'/ajax-loader.gif" alt="Loading" />'+
                            '</div>'
                        );
                $.ajax({
                    url: json_strings.uri.widgets+'statistics.php',
                    data: { days:days, ajax_call:true },
                    success: function(response){
                                target.html(response);
                            },
                    cache:false
                });
            }

            // Action log
            function ajax_widget_log( action ) {
                var target = $('.activities_log');
                target.html('<div class="loading-graph">'+
                                '<img src="'+json_strings.uri.assets_img+'/ajax-loader.gif" alt="Loading" />'+
                            '</div>'
                        );
                $.ajax({
                    url: json_strings.uri.widgets+'actions-log.php',
                    data: { action:action, ajax_call:true },
                    success: function(response){
                                target.html(response);
                            },
                    cache:false
                });
            }

            // Statistics
            $('.stats_days').click(function(e) {
                e.preventDefault();

                if ($(this).hasClass('btn-inverse')) {
                    return false;
                }
                else {
                    var days = $(this).data('days');
                    $('.stats_days').removeClass('btn-inverse');
                    $(this).addClass('btn-inverse');
                    ajax_widget_statistics(days);
                }
            });

            // Action log
            $('.log_action').click(function(e) {
                e.preventDefault();

                if ($(this).hasClass('btn-inverse')) {
                    return false;
                }
                else {
                    var action = $(this).data('action');
                    $('.log_action').removeClass('btn-inverse');
                    $(this).addClass('btn-inverse');
                    ajax_widget_log(action);
                }
            });

			ajax_widget_statistics(15);

			ajax_widget_log();
        });
    };
})();