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
                    maxlength: 100,
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
                    new_folder.dataset.folderId = response.data.data.id;
                    new_folder.querySelector('a').href = folder_url.replace('{folder_id}', response.data.data.id);
                    new_folder.querySelector('a span').innerText = folder_name;

                    const folders_nav = document.getElementById('folders_nav');
                    folders_nav.appendChild(new_folder);
                    addFolderDragEvents();
                })
                .catch(function (error) {
                    console.log(error);
                    // new Toast(error.response.data.error, Toast.TYPE_ERROR, Toast.TIME_NORMAL);
                });
            }
        }

        // Drag and drop events
        addFolderDragEvents();

        function addFolderDragEvents() {
            const folders_nav = document.getElementById('folders_nav');
            if (typeof(folders_nav) != 'undefined' && folders_nav != null)
            {
                let draggable_folders = document.querySelectorAll('.folder_draggable');
                for (let i = 0; i < draggable_folders.length; i++) {
                    // Start
                    draggable_folders[i].addEventListener("dragstart", function(e) {
                        // e.preventDefault();
                        draggable_folders[i].classList.add("dragging");
                    }, true);

                    // Stop
                    draggable_folders[i].addEventListener("dragend", function(e) {
                        // e.preventDefault();
                        draggable_folders[i].classList.remove("dragging");
                    }, true);
                }

                let draggable_destinations = document.querySelectorAll('.folder_destination');
                for (let i = 0; i < draggable_destinations.length; i++) {
                    // Drag another item over
                    draggable_destinations[i].addEventListener("dragover", function(e) {
                        e.preventDefault();
                        draggable_destinations[i].classList.add("drop_ready");
                    }, true);

                    // Stop drag over
                    draggable_destinations[i].addEventListener("dragleave", function(e) {
                        // e.preventDefault();
                        draggable_destinations[i].classList.remove("drop_ready");
                    }, true);

                    // Drop an element inside
                    draggable_destinations[i].addEventListener("drop", function(e) {
                        e.preventDefault();
                        draggable_destinations[i].classList.remove("drop_ready");
                        draggable_destinations[i].classList.add("dropped");
                        
                        const dragged_item = folders_nav.querySelector('.dragging');
                        const destiny = e.currentTarget;

                        const dragged_type = dragged_item.dataset.draggableType;

                        var data = new FormData();
                        data.append('csrf_token', document.getElementById('csrf_token').value);
        
                        switch (dragged_type) {
                            case 'folder':
                                data.append('folder_id', dragged_item.dataset.folderId);
                                data.append('new_parent_id', destiny.dataset.folderId);
                                dragged_item.classList.add('d-none');
                                axios.post(destiny.dataset.ondropUrl, data)
                                .then(function (response) {
                                    dragged_item.remove();
                                })
                                .catch(function (error) {
                                    dragged_item.classList.remove('d-none');
                                    // console.log(error);
                                    // new Toast(error.response.data.error, Toast.TYPE_ERROR, Toast.TIME_NORMAL);
                                });
                            break;
                            case 'file':
                            break;
                        }
                    }, true);
                }
            }
        }          
    };
})();