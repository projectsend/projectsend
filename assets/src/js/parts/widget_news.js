(function () {
    'use strict';
    
    admin.parts.widgetNews = function () {
        
        $(document).ready(function(){
            // Action log
            function ajax_widget_news( action ) {
                var target = $('#news_container');
                var list = $('<ul/>').addClass('none home_news list-unstyled');

                target.html('');
                $('#widget_projectsend_news .loading-icon').removeClass('none');

                $.ajax({
                    url: json_strings.uri.widgets+'ajax/news.php',
                    cache: false,
                }).done(function(data) {
                    var obj = JSON.parse(data);
                    console.log(obj);
                    $.each(obj.items, function(i, item) {
                        var li = $('<li/>')
                            .appendTo(list)
                            .html(`
                                <span class="date">`+item.date+`</span>
                                    <a href="`+item.url+`" target="_blank">
                                        <h5>`+item.title+`</h5>
                                    </a>
                                <p>`+item.content+`</p>
                            `);
                    });
                    //console.log(list);
                    target.append(list);
                    list.slideDown();
                }).fail(function(data) {
                    target.html(json_strings.translations.failed_loading_resource);
                }).always(function() {
                    $('#widget_projectsend_news .loading-icon').addClass('none');
                });
            }

			ajax_widget_news();
        });
    };
})();
