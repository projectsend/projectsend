(function () {
    'use strict';

    admin.parts.foldersAdmin = function () {
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

        function foldersDropTargetMake(except)
        {
            let draggable_folders = document.querySelectorAll('.folder_destination');
            for (let i = 0; i < draggable_folders.length; i++) {
                if (except != draggable_folders[i]) {
                    draggable_folders[i].classList.add("drop_target_active");
                }
                else {
                    draggable_folders[i].classList.add("drop_target_is_self");
                }
            }
        }

        function foldersDropTargetRemove()
        {
            let draggable_folders = document.querySelectorAll('.folder_destination');
            for (let i = 0; i < draggable_folders.length; i++) {
                draggable_folders[i].classList.remove("drop_target_active");
                draggable_folders[i].classList.remove("drop_target_is_self");
                draggable_folders[i].classList.remove("drop_forbidden");
            }
        }

        function addFolderDragEvents() {
            const folders_nav = document.getElementById('folders_nav');
            if (typeof(folders_nav) != 'undefined' && folders_nav != null)
            {
                let draggable_folders = document.querySelectorAll('.folder_destination');
                let draggable_files = document.querySelectorAll('.file_draggable');

                // Folders
                for (let i = 0; i < draggable_folders.length; i++) {
                    if (draggable_folders !== null) {
                        // Start
                        draggable_folders[i].addEventListener("dragstart", function(e) {
                            // e.preventDefault();
                            draggable_folders[i].classList.add("dragging");
                            foldersDropTargetMake(draggable_folders[i]);
                        }, true);

                        // Stop
                        draggable_folders[i].addEventListener("dragend", function(e) {
                            // e.preventDefault();
                            draggable_folders[i].classList.remove("dragging");
                            foldersDropTargetRemove();
                        }, true);
                    }
                }

                // Files
                if (draggable_files !== null) {
                    for (let i = 0; i < draggable_files.length; i++) {
                        // Start
                        draggable_files[i].addEventListener("dragstart", function(e) {
                            // e.preventDefault();
                            draggable_files[i].classList.add("dragging");
                            foldersDropTargetMake();
                        }, true);
    
                        // Stop
                        draggable_files[i].addEventListener("dragend", function(e) {
                            // e.preventDefault();
                            draggable_files[i].classList.remove("dragging");
                            foldersDropTargetRemove();
                        }, true);
                    }
                }

                let draggable_destinations = document.querySelectorAll('.folder_destination');
                if (draggable_destinations !== null) {
                    for (let i = 0; i < draggable_destinations.length; i++) {
                        // Drag another item over
                        draggable_destinations[i].addEventListener("dragover", function(e) {
                            e.preventDefault();
                            const dragged_item = document.querySelector('.dragging');
                            if (e.currentTarget.dataset.folderId == dragged_item.dataset.folderId) {
                                draggable_destinations[i].classList.add("drop_forbidden");
                            } else {
                                draggable_destinations[i].classList.add("drop_ready");
                            }
                        }, true);

                        // Stop drag over
                        draggable_destinations[i].addEventListener("dragleave", function(e) {
                            // e.preventDefault();
                            draggable_destinations[i].classList.remove("drop_forbidden");
                            draggable_destinations[i].classList.remove("drop_ready");
                        }, true);

                        // Drop an element inside
                        draggable_destinations[i].addEventListener("drop", function(e) {
                            let url;
                            e.preventDefault();
                            draggable_destinations[i].classList.remove("drop_forbidden");
                            draggable_destinations[i].classList.remove("drop_ready");
                            draggable_destinations[i].classList.add("dropped");
                            
                            const dragged_item = document.querySelector('.dragging');
                            const destiny = e.currentTarget;

                            const dragged_type = dragged_item.dataset.draggableType;
                            if (e.currentTarget.dataset.folderId == dragged_item.dataset.folderId) {
                                return;
                            }

                            var data = new FormData();
                            data.append('csrf_token', document.getElementById('csrf_token').value);
                            data.append('new_parent_id', destiny.dataset.folderId);
            
                            switch (dragged_type) {
                                case 'folder':
                                    url = document.getElementById('folder_context_menu__links').dataset.urlFolderOndrop;
                                    data.append('folder_id', dragged_item.dataset.folderId);
                                    dragged_item.classList.add('d-none');
                                    axios.post(url, data)
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
                                    url = document.getElementById('folder_context_menu__links').dataset.urlFileOndrop;
                                    data.append('file_id', dragged_item.dataset.fileId);
                                    dragged_item.classList.add('d-none');
                                    axios.post(url, data)
                                    .then(function (response) {
                                        dragged_item.remove();
                                    })
                                    .catch(function (error) {
                                        dragged_item.classList.remove('d-none');
                                    });
                                break;
                            }
                        }, true);
                    }
                }

                // Context menu
                let folders_items = document.querySelectorAll('.folder');
                
                for (let i = 0; i < folders_items.length; i++) {
                    const can_edit = folders_items[i].dataset.canEdit;
                    const can_delete = folders_items[i].dataset.canDelete;

                    let menu_items = [];

                    menu_items.push({
                        label: 'Navigate',
                        iconClass: 'fa fa-arrow-right',
                        callback: async () => {
                            folderNavigate(folders_items[i]);
                        }
                    });
                    menu_items.push('hr');

                    if (can_edit == 'true') {
                        menu_items.push({
                            label: 'Rename',
                            iconClass: 'fa fa-pencil',
                            callback: async () => {
                                folderRename(folders_items[i]);
                            }
                        });
                        menu_items.push('hr');
                    }

                    if (can_delete == 'true') {
                        menu_items.push({
                            label: 'Delete',
                            iconClass: 'fa fa-trash-o',
                            callback: async () => {
                                folderDelete(folders_items[i]);
                            }
                        });
                    }

                    new VanillaContextMenu({
                        scope: folders_items[i],
                        customThemeClass: 'context-menu-orange-theme',
                        customClass: 'custom-context-menu-cls',
                        menuItems: menu_items
                    });
                }
            }
        }

        function folderNavigate(folder)
        {
            window.location = folder.querySelectorAll('a')[0].href;
        }

        // function folderShare(folder)
        // {
        //     const url = document.getElementById('folder_context_menu__links').dataset.urlShare;
        // }

        async function folderRename(folder)
        {
            const url = document.getElementById('folder_context_menu__links').dataset.urlRename;
            const name_container = folder.querySelectorAll('a span')[0];
            const previous_name = name_container.innerHTML;
            const { value: new_name } = await Swal.fire({
                title: null,
                input: 'text',
                inputValue: previous_name,
                showCancelButton: true,
                inputAttributes: {
                    maxlength: 100,
                    autocapitalize: 'off',
                    autocorrect: 'off'
                },
                inputValidator: (value) => {
                    if (!value) {
                        return 'Name is not valid'
                    }
                }
            })
                
            if (new_name) {
                var data = new FormData();
                data.append('csrf_token', document.getElementById('csrf_token').value);
                data.append('folder_id', folder.dataset.folderId);
                data.append('name', new_name);
                name_container.innerHTML = new_name;
                
                axios.post(url, data)
                .then(function (response) {
                    folder.dataset.name = new_name;
                })
                .catch(function (error) {
                    name_container.innerHTML = previous_name;
                    new Toast(error.response.data.error, Toast.TYPE_ERROR, Toast.TIME_NORMAL);
                });
            }
        }

        function folderDelete(folder)
        {
            const url = document.getElementById('folder_context_menu__links').dataset.urlDelete;
            const folder_name = folder.dataset.name;
            const html = `Delete folder ${folder_name} and all of its contents?`

            Swal.fire({
                title: 'Delete folder',
                html: html,
                showCloseButton: false,
                showCancelButton: true,
                showConfirmButton: true,
                focusCancel: true,
                cancelButtonText: 'Cancel',
                confirmButtonText: 'Delete',
                buttonsStyling: false,
                reverseButtons: true,
                customClass: {
                    cancelButton: 'btn btn-lg btn-secondary',
                    confirmButton: 'btn btn-lg btn-danger ms-4'
                },
                showClass: {
                    popup: 'animate__animated animated__fast animate__fadeInUp'
                },
                hideClass: {
                    popup: 'animate__animated animated__fast animate__fadeOutDown'
                }
            }).then((result) => {
                if (result.value) {
                    folder.classList.add('d-none');

                    var data = new FormData();
                    data.append('csrf_token', document.getElementById('csrf_token').value);
                    data.append('folder_id', folder.dataset.folderId);
                    
                    axios.post(url, data)
                    .then(function (response) {
                        folder.remove();
                    })
                    .catch(function (error) {
                        folder.classList.remove('d-none');
                        new Toast(error.response.data.error, Toast.TYPE_ERROR, Toast.TIME_NORMAL);
                    });
                }
            });

        }
    };
})();