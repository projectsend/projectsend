(function () {
    'use strict';
    
    admin.parts.widgetStatistics = function () {
        
        $(document).ready(function(){
            var chart;

            // Statistics chart
            function ajax_widget_statistics(days) {
                var _chart_container = $('#widget_statistics #chart_container');
                _chart_container.find('canvas').remove();
                $('#widget_statistics .loading-icon').removeClass('none');
                if (chart) {
                    chart.destroy();
                }
                $.ajax({
                    url: json_strings.uri.widgets+'ajax/statistics.php',
                    data: { days:days },
                    cache: false,
                }).done(function(data) {
                    // var obj = JSON.parse(data);
                    var obj = data;
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
                    _chart_container.html(json_strings.translations.failed_loading_resource);
                }).always(function() {
                    $('#widget_statistics .loading-icon').addClass('none');
                });
    
                return;
            }

            // Statistics
            $('#widget_statistics button.get_statistics').on('click', function(e) {
                if ($(this).hasClass('active')) {
                    return false;
                }
                else {
                    var days = $(this).data('days');
                    $('#widget_statistics button.get_statistics').removeClass('btn-primary active').addClass('btn-pslight');
                    $(this).addClass('btn-primary active').removeClass('btn-pslight');
                    ajax_widget_statistics(days);
                }
            });

			ajax_widget_statistics(15);
        });
    };
})();