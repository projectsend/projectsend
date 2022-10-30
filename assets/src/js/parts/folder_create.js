(function () {
    'use strict';

    admin.parts.folderCreate = function () {
        let btn_create = document.getElementById('btn_header_folder_create');
        btn_create.addEventListener('click', function(e) {
            createFolderModal();
        });

        async function createFolderModal()
        {
            const { value: folder_name } = await Swal.fire({
                title: btn_create.dataset.modalTitle,
                input: 'text',
                inputLabel: btn_create.dataset.modalLabel,
                inputValue: null,
                showCancelButton: true,
                inputAttributes: {
                    maxlength: 32,
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                inputValidator: (value) => {
                    if (!value) {
                        return btn_create.dataset.modalTitleInvalid
                    }
                }
            })
              
            if (folder_name) {
                var data = new FormData();
                data.append('csrf_token', document.getElementById('csrf_token').value);
                data.append('folder_name', folder_name);
                data.append('folder_parent', btn_create.dataset.parent);
                
                axios.post(btn_create.dataset.processUrl, data)
                .then(function (response) {
                    const folder_url = btn_create.dataset.folderUrl;
                    const folder_template = document.getElementById('new_folder').content.cloneNode(true);
                    let new_folder = folder_template.querySelectorAll('div')[0];
                    new_folder.classList.add('new_flash');
                    new_folder.querySelector('a').href = folder_url.replace('{folder_id}', response.data.data.id);
                    new_folder.querySelector('a span').innerText = folder_name;

                    const folders_nav = document.getElementById('folders_nav');
                    folders_nav.appendChild(new_folder);
                    // addFolderToGrid(tool_element, values);
                })
                .catch(function (error) {
                    console.log(error);
                    // new Toast(error.response.data.error, Toast.TYPE_ERROR, Toast.TIME_NORMAL);
                });

            }
        }
    };
})();