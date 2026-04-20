(function () {
    'use strict';

    admin.parts.changelogModal = function () {
        var trigger = document.querySelector('.changelog-trigger');
        if (!trigger) return;

        var modalEl = document.getElementById('changelogModal');
        var modalBody = document.getElementById('changelogModalBody');
        var modal = new bootstrap.Modal(modalEl);
        var loaded = false;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();

            modal.show();

            if (loaded) return;

            var version = trigger.dataset.version;
            var url = json_strings.uri.base + 'assets/changelogs/' + version + '.md';

            fetch(url)
                .then(function (response) {
                    if (!response.ok) throw new Error('Not found');
                    return response.text();
                })
                .then(function (md) {
                    modalBody.innerHTML = '<div class="changelog-content">' + marked.parse(md) + '</div>';
                    loaded = true;
                })
                .catch(function () {
                    modalBody.innerHTML = '<p class="text-muted">' + 'Changelog not available.' + '</p>';
                });
        });
    };
})();
