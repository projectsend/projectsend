(function () {
    'use strict';

    admin.pages.emailTemplates = function () {

        $(document).ready(function(){
            $(document).on('click', '.load_default', function(e) {
                e.preventDefault();

                var file = jQuery(this).data('file');
                var textarea = document.getElementById(jQuery(this).data('textarea'));
                var accept = confirm(json_strings.translations.email_templates.confirm_replace);
                
                if ( accept ) {
                    $.ajax({
                        url: "emails/"+file,
                        cache: false,
                        success: function (data){
                            textarea.value = data;
                        },
                        error: function() {
                            alert(json_strings.translations.email_templates.loading_error);
                        }
                    });
                }
            });
    
            $('.preview').click(function(e) {
                e.preventDefault();
                var type = jQuery(this).data('preview');
                var url = json_strings.uri.base+ 'email-preview.php?t=' + type;
                window.open(url, "previewWindow", "width=800,height=600,scrollbars=yes");
            });
        });

        $('.insert_tag').on('click', function(e) {
            var target = jQuery(this).data('target');
            insertAtCaret(target, $(this).data('tag'));
        });

        // Check if each tag is used or not
        var tags_dt = document.querySelectorAll('#email_available_tags dt button');
        var tags = [];
        Array.prototype.forEach.call(tags_dt, function(tag) {
            tags.push(tag.dataset.tag);
        });

        var textareas = document.querySelectorAll('#form_email_template textarea');

        const check_tags_usage = setInterval(() => {
            tags.forEach(tag => {
                checkTagsUsage(tag);
            });
        }, 1000);

        function checkTagsUsage(tag)
        {
            textareas.forEach(element => {
                const el = document.querySelector('button[data-tag="'+tag+'"]');
                if (!element.value.includes(tag)) {
                    el.classList.add('btn-warning');
                    el.classList.remove('btn-pslight');
                } else {
                    el.classList.add('btn-pslight');
                    el.classList.remove('btn-warning');
                }
            });
        }
    };
})();