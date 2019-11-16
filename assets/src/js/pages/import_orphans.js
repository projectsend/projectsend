(function () {
    'use strict';

    admin.pages.importOrphans = function () {

        $(document).ready(function(){
            $("#upload_by_ftp").submit(function() {
				var checks = $("td>input:checkbox").serializeArray(); 
				
				if (checks.length == 0) { 
					alert(json_strings.translations.select_one_or_more);
					return false; 
				} 
			});
			
			/**
			 * Only select the current file when clicking an "edit" button
			 */
			$('.btn-edit-file').click(function(e) {
				$('#select_all').prop('checked', false);
				$('td .select_file_checkbox').prop('checked', false);
				$(this).parents('tr').find('td .select_file_checkbox').prop('checked', true);
				$('#upload-continue').click();
			});
        });
    };
})();