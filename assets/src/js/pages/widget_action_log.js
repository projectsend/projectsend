(function () {
    'use strict';
    
    admin.parts.widgetActionLog = function () {
        
        $(document).ready(function(){
            // Action log
            function ajax_widget_log( action ) {
                var target = $('#log_container');
                var select = $('#widget_actions_log_change');
                var list = $('<ul/>').addClass('none');

                target.html('');
                select.attr('disabled', 'disabled');
                $('#widget_actions_log .loading-icon').removeClass('none');

                $.ajax({
                    url: json_strings.uri.widgets+'ajax/actions-log.php',
                    data: { action:action },
                    cache: false,
                }).done(function(data) {
                    var obj = JSON.parse(data);
                    //console.log(obj);
                    $.each(obj.actions, function(i, item) {
                        var line = [];
                        var parts = {
                            part1: item.part1,
                            action: item.action,
                            part2: item.part2,
                            part3: item.part3,
                            part4: item.part4,
                        }

                        for (const key in parts) {
                            if (parts[key]) {
                                line.push('<span class="item_'+key+'">'+parts[key]+'</span>');
                            }
                        };

                        var icon;
                        switch (item.type) {
                            case 'system': icon = 'cog'; break;
                            case 'auth': icon = 'lock'; break;
                            case 'files': icon = 'file'; break;
                            case 'clients': icon = 'address-card'; break;
                            case 'users': icon = 'users'; break;
                            case 'groups': icon = 'th-large'; break;
                            case 'categories': icon = 'object-group'; break;
                            default: icon = 'cog'; break;
                        }
                        line = line.join(' ');
                        var li = $('<li/>')
                            .appendTo(list)
                            .html(`
                                <div class="date">
                                    <span class="label label-default">`+
                                        item.timestamp+`
                                    </span>
                                </div>
                                <div class="action">
                                    <i class="fa fa-`+icon+`" aria-hidden="true"></i>`+ 
                                    line+`
                                </div>
                            `);
                    });
                    //console.log(list);
                    target.append(list);
                    list.slideDown();
                }).fail(function(data) {
                    target.html(json_strings.translations.failed_loading_resource);
                }).always(function() {
                    $('#widget_actions_log .loading-icon').addClass('none');
                });

                $(select).removeAttr('disabled');
            }

            // Action log
            $('#widget_actions_log_change').on('change', function(e) {
                var action = $('#widget_actions_log_change option').filter(':selected').val()
                ajax_widget_log(action);
            });

			ajax_widget_log();
        });
    };
})();