(function () {
    'use strict';

    admin.parts.widgetStatistics = function () {

        $(document).ready(function(){
            var chart;

            // Get current theme colors
            function getThemeColors() {
                var isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
                return {
                    textColor: isDarkMode ? '#e9ecef' : '#333333',
                    gridColor: isDarkMode ? '#4a5568' : '#e5e5e5',
                    tooltipBg: isDarkMode ? '#2d3748' : '#fff'
                };
            }

            // Update chart colors for current theme
            function updateChartColors() {
                if (!chart) return;

                var colors = getThemeColors();

                // Update legend
                if (chart.options.legend && chart.options.legend.labels) {
                    chart.options.legend.labels.fontColor = colors.textColor;
                }

                // Update scales
                if (chart.options.scales) {
                    if (chart.options.scales.xAxes) {
                        chart.options.scales.xAxes.forEach(axis => {
                            if (axis.ticks) axis.ticks.fontColor = colors.textColor;
                            if (axis.gridLines) axis.gridLines.color = colors.gridColor;
                        });
                    }
                    if (chart.options.scales.yAxes) {
                        chart.options.scales.yAxes.forEach(axis => {
                            if (axis.ticks) axis.ticks.fontColor = colors.textColor;
                            if (axis.gridLines) axis.gridLines.color = colors.gridColor;
                        });
                    }
                }

                // Update tooltips
                if (chart.options.tooltips) {
                    chart.options.tooltips.backgroundColor = colors.tooltipBg;
                    chart.options.tooltips.titleFontColor = colors.textColor;
                    chart.options.tooltips.bodyFontColor = colors.textColor;
                    chart.options.tooltips.borderColor = colors.gridColor;
                }

                chart.update();
            }

            // Listen for theme changes
            document.addEventListener('themechange', function() {
                updateChartColors();
            });

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
                    _chart_container.append('<canvas id="chart_statistics"></canvas>');

                    // Get theme colors using centralized function
                    var colors = getThemeColors();

                    chart = new Chart(document.getElementById('chart_statistics'), {
                        type: 'line',
                        data: obj.chart,
                        options: {
                            responsive: true,
                            plugins: {
                                title: {
                                    display: false
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    backgroundColor: colors.tooltipBg,
                                    titleColor: colors.textColor,
                                    bodyColor: colors.textColor,
                                    borderColor: colors.gridColor,
                                    borderWidth: 1
                                },
                                legend: {
                                    labels: {
                                        color: colors.textColor
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    ticks: {
                                        color: colors.textColor
                                    },
                                    grid: {
                                        color: colors.gridColor
                                    }
                                },
                                y: {
                                    display: true,
                                    ticks: {
                                        color: colors.textColor
                                    },
                                    grid: {
                                        color: colors.gridColor
                                    }
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