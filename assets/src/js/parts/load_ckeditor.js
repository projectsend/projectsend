(function () {
    'use strict';

    admin.parts.loadCKEditor = function () {

        $(document).ready(function() {
            if (document.querySelector('textarea.ckeditor') !== null) {
                // CKEditor
                ClassicEditor
                    .create( document.querySelector( '.ckeditor' ), {
                        removePlugins: [ 'Heading', 'Link' ],
                        toolbar: [ 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote' ]
                    })
                    .then( editor => {
                        window.editor = editor;
                    } )
                    .catch( error => {
                        console.error( 'There was a problem initializing the editor.', error );
                    } );
            }
        });
    }
})();