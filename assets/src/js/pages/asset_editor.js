(function () {
    'use strict';

    admin.pages.assetEditor = function () {
        $(document).ready(function()
        {
            const types_allowed = ['js', 'css', 'html'];
            const type = $('#asset_language').val();
            var mode;
            if (types_allowed.includes(type)) {
                switch (type) {
                    case 'css': mode = 'css'; break;
                    case 'js': mode = 'javascript'; break;
                    case 'html': mode = 'htmlmixed'; break;
                }
            }
            // console.log(mode);
            var editor = CodeMirror.fromTextArea(document.getElementById("content"), {
                lineNumbers: true,
                mode: mode,
                //theme: 'neo',
                lineWrapping: true
            });
        });
    };
})();

