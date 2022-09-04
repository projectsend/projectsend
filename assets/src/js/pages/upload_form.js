(function () {
    'use strict';

    admin.pages.uploadForm = function () {

        $(document).ready(function(){
            var file_ids = [];
            var errors = 0;
            var successful = 0;
            window.file_data = [];

            window.smd.changeCloseWithButtonOnly(true);

            function generateRandomKey() {
                return sjcl.codec.base64.fromBits(sjcl.random.randomWords(8, 10), 0);
            }

            // Send a keep alive action every 1 minute
            setInterval(function(){
                var timestamp = new Date().getTime()
                $.ajax({
                    type:	'GET',
                    cache:	false,
                    url:	'includes/ajax-keep-alive.php',
                    data:	'timestamp='+timestamp,
                    success: function(result) {
                        var dummy = result;
                    }
                });
            },1000*60);

            var uploader = $('#uploader').pluploadQueue();

            $(document).on('click', '.copy_key', function(e) {
                copyTextToClipboard($(this).data('key'));
            });

            $(document).on('click', '#upload_encrypt_continue', function(e) {
                if (confirm(json_strings.translations.confirm_copied_all_keys)) {
                    window.smd.closeSideModal();
                    $('#upload_form').submit();
                }
            });

            $(document).on('click', '#upload_encrypt_cancel', function(e) {
                // if (confirm(json_strings.translations.confirm_cancel_copy_keys)) {
                window.smd.closeSideModal();
                $('#btn-submit').removeAttr('disabled');
                // }
            });

            var files_keys = [];
            var template = document.getElementsByClassName("encrypt_warning")[0].cloneNode(true);
            var tbody = template.getElementsByTagName('table')[0].getElementsByTagName('tbody')[0];
            template.classList.remove('hidden');

            function shouldEncrypt()
            {
                var encrypt_checkbox = $('#browser_encrypt');
                return encrypt_checkbox.is(':checked');
            }

            $(document).on('click', '#btn-submit', function(e) {
                if (uploader.files.length > 0) {
                    // No encryption
                    if (!shouldEncrypt()) {
                        $('#upload_form').submit();
                        return;
                    }

                    // Enable browser encryption
                    tbody.innerHTML = ''; // Reset the keys table
                    uploader.files.forEach((file) => {
                        // console.log(file);
                        if (!files_keys[file.uid]){
                            var key = generateRandomKey();
                            files_keys[file.uid] = {
                                uid: file.uid,
                                name: file.name,
                                size: file.origSize,
                                key: key
                            };
                        } else {
                            var key = files_keys[file.uid]['key'];
                        }
                        var tr = document.createElement("tr");
                        var button = document.createElement('button');
                        button.classList.add("copy_key", "btn", "btn-default");
                        button.dataset.key = key;
                        button.innerHTML = '<i class="fa fa-files-o" aria-hidden="true"></i> '+json_strings.translations.click_to_copy;
                        var tds = [
                            document.createTextNode(file.name),
                            document.createTextNode(key),
                            button
                        ];

                        for (var key in tds) {
                            var td = document.createElement("td");
                            td.appendChild(tds[key]);
                            tr.appendChild(td);
                        }

                        tbody.appendChild(tr);
                    });

                    // Side modal
                    window.smd.clean();
                    window.smd.setTitle(json_strings.translations.file_encryption_modal_title);
                    window.smd.setContent(template.outerHTML);
                    window.smd.openSideModal();
            
                    $('#btn-submit').attr('disabled', 'disabled');
                    //console.log(files_keys);
                } else {
                    toastr.error(json_strings.translations.upload_no_files_selected)
                }
            });

            // uploader.bind('FilesAdded', function(up, files) {
            //     var fr = new plupload.FileReader();
            //     fr.onload = function() {
            //         var dataUrl = this.result;
            //         console.log(dataUrl);
            //     };
            //     fr.readAsDataURL(files[0].getSource());
            // });

            async function encryptAndReplaceFiles(uploader) {
                console.log('Encrypting ' + uploader.files.length + ' files');
                // uploader.files.forEach((file, i) => {
                for await (const file of uploader.files) {
                    let file_data = files_keys[file.uid];
                    var reader = new plupload.FileReader();
                    reader.onload = function(ev) {
                        // console.log(file_data);
                        const dataURL = ev.target.result;
                        const base64 = dataURL.slice(dataURL.indexOf(',')+1);

                        // Encrypt
                        var key = file_data['key'];
                        var encrypted = sjcl.encrypt(key, base64);
                        // console.log(encrypted);

                        uploader.removeFile(file);
                        var new_file = new File([encrypted], 'encrypted.'+file_data['name']);
                        var nf = uploader.addFile(new_file, 'encrypted.'+file_data['name']);

                        // Check
                        // var decrypted = sjcl.decrypt(key, encrypted);
                        // console.log(decrypted);

                        console.log('Encrypted: ' + file_data['name']);
                    };
                    reader.onerror = err => console.log(err);
    
                    var bin = reader.readAsDataURL(file.getSource());
                        
                    // Get file checksum
                    // var readerab = new FileReader();
                    // readerab.onload = e => {
                    //     crypto.subtle.digest('SHA-256', e.target.result).then(hashBuffer => {
                    //         // Convert hex to hash, see https://developer.mozilla.org/en-US/docs/Web/API/SubtleCrypto/digest#converting_a_digest_to_a_hex_string
                    //         const hashArray = Array.from(new Uint8Array(hashBuffer));
                    //         const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join(''); // convert bytes to hex string
                    //         console.log(hashHex);
                    //     }).catch(ex => console.error(ex));
                    // }
                    // readerab.readAsArrayBuffer(native);
                };
            };

            $('#upload_form').on('submit', function(e) {
                if (uploader.files.length > 0) {
                    if (shouldEncrypt()) {
                        encryptAndReplaceFiles(uploader);
                    }

                    uploader.bind('StateChanged', function() {
                        if (uploader.files.length === (uploader.total.uploaded + uploader.total.failed)) {
                            var action = $('#upload_form').attr('action') + '?ids=' + file_ids.toString() + '&type=new';
                            $('#upload_form').attr('action', action);
                            if (successful > 0) {
                                if (errors == 0) {
                                    window.location = action;
                                } else {
                                    $(`
                                        <div class="alert alert-info">`+json_strings.translations.upload_form.some_files_had_errors+`</div>
                                        <a class="btn btn-wide btn-primary" href="`+action+`">`+json_strings.translations.upload_form.continue_to_editor+`</a>
                                    `).insertBefore( "#upload_form" );
                                }
                                return;
                            }
                            // $('#upload_form')[0].submit();
                        }
                    });

                    uploader.start();

                    $("#btn-submit").hide();
                    $(".message_uploading").fadeIn();

                    uploader.bind('Error', function(uploader, error) {
                        var obj = JSON.parse(error.response);
                        $(
                            `<div class="alert alert-danger">`+obj.error.filename+`: `+obj.error.message+`</div>`
                        ).insertBefore( "#upload_form" );
                        //console.log(obj);
                    });
        
                    uploader.bind('FileUploaded', function (uploader, file, info) {
                        var obj = JSON.parse(info.response);
                        file_ids.push(obj.info.id);
                        successful++;
                    });

                    return false;
                } else {
                    alert(json_strings.translations.upload_form.no_files);
                }

                return false;
            });

            window.onbeforeunload = function (e) {
                var e = e || window.event;

                console.log('state? ' + uploader.state);

                // if uploading
                if(uploader.state === 2) {
                    //IE & Firefox
                    if (e) {
                        e.returnValue = json_strings.translations.upload_form.leave_confirm;
                    }

                    // For Safari
                    return json_strings.translations.upload_form.leave_confirm;
                }
            };
        });
    };
})();