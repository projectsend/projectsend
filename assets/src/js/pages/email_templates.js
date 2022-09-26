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
                var type	= jQuery(this).data('preview');
                var url		= json_strings.uri.base+ 'email-preview.php?t=' + type;
                window.open(url, "previewWindow", "width=800,height=600,scrollbars=yes");
            });
        });

        $('.insert_tag').on('click', function(e) {
            var target = jQuery(this).data('target');
            insertAtCaret(target, $(this).data('tag'));
        });
    };
})();