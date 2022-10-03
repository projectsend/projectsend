/**
 * Apply bulk actions
 */
(function () {
    'use strict';

    admin.parts.bulkActions = function () {

        $(document).ready(function(e) {
            $(".batch_actions").on('submit', function(e) {
                var checks = $("td>input:checkbox").serializeArray();
                var action = $('#action').val();
                if (action != 'none') {
                        // Generic actions
                        if (action == 'delete') {
                            if (checks.length == 0) {
                                alert(json_strings.translations.select_one_or_more);
                                return false;
                            }
                            else {
                                var _formatted = sprintf(json_strings.translations.confirm_delete, checks.length);
                                if (!confirm(_formatted)) {
                                    e.preventDefault();
                                }
                            }
                        }

                        // Activities log actions
                        if (action == 'log_clear') {
                            var msg = json_strings.translations.confirm_delete_log;
                            if (!confirm(msg)) {
                                e.preventDefault();
                            }
                        }

                        if (action == 'log_download') {
                            e.preventDefault();

                            let modal_content = `<p class="loading-icon">
                                <img src="`+json_strings.uri.assets_img+`/loading.svg" alt="Loading" /></p>
                                <p class="lead">`+json_strings.translations.download_wait+`</p>
                            `;

                            Cookies.set('log_download_started', 0, { expires: 100 });
                            setTimeout(check_log_download_cookie, 1000);

                            Swal.fire({
                                title: '',
                                html: modal_content,
                                showCloseButton: false,
                                showCancelButton: false,
                                showConfirmButton: false,
                                showClass: {
                                    popup: 'animate__animated animated__fast animate__fadeInUp'
                                },
                                hideClass: {
                                    popup: 'animate__animated animated__fast animate__fadeOutDown'
                                },
                                didOpen: function () {
                                    var url = json_strings.uri.base+'includes/actions.log.export.php?format=csv';
                                    let iframe = document.createElement('iframe');
                                    let swalcontainer = document.getElementById('swal2-html-container')
                                    swalcontainer.appendChild(iframe);
                                    iframe.setAttribute('src', url);
                                }
                            }).then((result) => {
                            });
                        }

                        if (action == 'cron_log_download') {
                            e.preventDefault();

                            let modal_content = `<p class="loading-icon">
                                <img src="`+json_strings.uri.assets_img+`/loading.svg" alt="Loading" /></p>
                                <p class="lead">`+json_strings.translations.download_wait+`</p>
                            `;

                            Cookies.set('log_download_started', 0, { expires: 100 });
                            setTimeout(check_log_download_cookie, 1000);

                            Swal.fire({
                                title: '',
                                html: modal_content,
                                showCloseButton: false,
                                showCancelButton: false,
                                showConfirmButton: false,
                                showClass: {
                                    popup: 'animate__animated animated__fast animate__fadeInUp'
                                },
                                hideClass: {
                                    popup: 'animate__animated animated__fast animate__fadeOutDown'
                                },
                                didOpen: function () {
                                    var url = json_strings.uri.base+'includes/cron.log.export.php?format=csv';
                                    let iframe = document.createElement('iframe');
                                    let swalcontainer = document.getElementById('swal2-html-container')
                                    swalcontainer.appendChild(iframe);
                                    iframe.setAttribute('src', url);
                                }
                            }).then((result) => {
                            });
                        }

                        // Manage files actions
                        if (action == 'unassign') {
                            var _formatted = sprintf(json_strings.translations.confirm_unassign, checks.length);
                            if (!confirm(_formatted)) {
                                e.preventDefault();
                            }
                        }

                        // Download multiple files as zip
                        if (action == 'zip') {
                            e.preventDefault();
                            var checkboxes = $("td>input:checkbox:checked").serializeArray();
                            if (checkboxes.length > 0) {
                                let modal_content = `<p class="loading-icon"><img src="`+json_strings.uri.assets_img+`/loading.svg" alt="Loading" /></p>
                                    <p class="lead">`+json_strings.translations.download_wait+`</p>
                                    <p class="">`+json_strings.translations.download_long_wait+`</p>
                                `;

                                Cookies.set('download_started', 0, { expires: 100 });
                                setTimeout(check_download_cookie, 1000);

                                Swal.fire({
                                    title: '',
                                    html: modal_content,
                                    showCloseButton: false,
                                    showCancelButton: false,
                                    showConfirmButton: false,
                                    showClass: {
                                        popup: 'animate__animated animated__fast animate__fadeInUp'
                                    },
                                    hideClass: {
                                        popup: 'animate__animated animated__fast animate__fadeOutDown'
                                    },
                                    didOpen: function () {
                                        $.ajax({
                                            method: 'GET',
                                            url: json_strings.uri.base + 'process.php',
                                            data: { do:"return_files_ids", files:checkboxes }
                                        }).done( function(rsp) {
                                            var url = json_strings.uri.base + 'process.php?do=download_zip&files=' + rsp;
                                            let iframe = document.createElement('iframe');
                                            let swalcontainer = document.getElementById('swal2-html-container')
                                            swalcontainer.appendChild(iframe);
                                            iframe.setAttribute('src', url);
                                        });
                                    }
                                }).then((result) => {
                                });
                            }
                        }
                }
                else {
                    return false;
                }
            });
        });
    };
})();