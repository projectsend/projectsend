(function () {
    'use strict';

    admin.parts.downloadCookieHandler = function () {
        $(document).ready(function() {
                /**
             * CLOSE THE ZIP DOWNLOAD MODAL
             * Solution to close the modal. Suggested by remez, based on
             * https://stackoverflow.com/questions/29532788/how-to-display-a-loading-animation-while-file-is-generated-for-download
             */
            var downloadTimeout;
            window.check_download_cookie = function() {
                if (Cookies.get("download_started") == 1) {
                    Cookies.set("download_started", "false", { expires: 100 });
                    remove_modal();
                } else {
                    downloadTimeout = setTimeout(check_download_cookie, 1000);
                }
            };

            // Close the log CSV download modal
            var logdownloadTimeout;
            window.check_log_download_cookie = function() {
                if (Cookies.get("log_download_started") == 1) {
                    Cookies.set("log_download_started", "false", { expires: 100 });
                    remove_modal();
                } else {
                    logdownloadTimeout = setTimeout(check_log_download_cookie, 1000);
                }
            };
        });
    }
})();