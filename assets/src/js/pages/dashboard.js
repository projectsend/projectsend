(function () {
    'use strict';
    
    admin.pages.dashboard = function () {
        
        $(document).ready(function(){
            var chart;

            // Statistics chart
            function ajax_widget_statistics(days) {
                var _chart_container = $('.widget_statistics #chart_container');
                _chart_container.find('canvas').remove();
                $('.widget_statistics .chart_loading').removeClass('none');
                if (chart) {
                    chart.destroy();
                }
                $.ajax({
                    url: json_strings.uri.widgets+'ajax/statistics.php',
                    data: { days:days },
                    cache: false,
                }).done(function(data) {
                    var obj = JSON.parse(data);
                    _chart_container.append('<canvas id="chart_statistics"><canvas>');
                    chart = new Chart(document.getElementById('chart_statistics'), {
                        type: 'line',
                        data: obj.chart,
                        options: {
                            responsive: true,
                            title: {
                                display: false
                            },
                            tooltips: {
                                mode: 'index',
                                intersect: false
                            },
                            scales: {
                                xAxes: [{
                                    display: true,
                                }],
                                yAxes: [{
                                    display: true
                                }]
                            },
                            elements: {
                                line: {
                                    tension: 0
                                }
                            }
                        }
                    });
                }).fail(function(data) {
                }).always(function() {
                    $('.widget_statistics .chart_loading').addClass('none');
                });
    
                return;
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

                if ($(this).hasClass('active')) {
                    return false;
                }
                else {
                    var days = $(this).data('days');
                    $('.stats_days').removeClass('btn-inverse active');
                    $(this).addClass('btn-inverse active');
                    ajax_widget_statistics(days);
                }
            });

            // Action log
            $('.log_action').click(function(e) {
                e.preventDefault();

                if ($(this).hasClass('active')) {
                    return false;
                }
                else {
                    var action = $(this).data('action');
                    $('.log_action').removeClass('btn-inverse active');
                    $(this).addClass('btn-inverse active');
                    ajax_widget_log(action);
                }
            });

			ajax_widget_statistics(15);

			ajax_widget_log();
        });
    };
})();