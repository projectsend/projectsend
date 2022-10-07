(function () {
    'use strict';

    admin.parts.passwordVisibilityToggle = function () {
        let password_inputs = document.querySelectorAll('.attach_password_toggler')
        password_inputs.forEach(element => {
            var id;
            if (element.hasAttribute('id')) {
                id = element.id;
            } else {
                let random = (Math.random() + 1).toString(36).substring(7);
                id = 'el_pass_'+random;
                element.setAttribute('id', id);
            }

            let button = document.createElement('button');
            button.classList.add('btn', 'btn-sm', 'btn-light', 'input-group-btn', 'password_toggler');
            button.setAttribute('type', 'button');
            button.dataset.targetId = id;
            button.dataset.status = 'hidden';

            let icon = document.createElement('i');
            icon.classList.add('fa', 'fa-eye');
            button.appendChild(icon);

            var copy = element.cloneNode();
            var wrapper = document.createElement('div');
            wrapper.classList.add('input-group');
            wrapper.appendChild(copy);
            wrapper.appendChild(button);

            element.insertAdjacentElement("afterend", wrapper);
            element.remove();

            button.addEventListener( 'click', function ( event ) {
                let id = event.target.dataset.targetId;
                let target = document.getElementById(id);
                let me = event.target;
                let icon = this.querySelector('i');
                let type;
                switch (me.dataset.status) {
                    case 'hidden':
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                        me.dataset.status = 'visible';
                        type = 'text';
                    break;
                    case 'visible':
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        me.dataset.status = 'hidden';
                        type = 'password';
                    break;
                }

                let new_input = document.createElement('input');
                new_input.setAttribute('type', type);
                new_input.setAttribute('id', id);
                new_input.setAttribute('name', target.name);
                if (target.hasAttribute('max-length')) {
                    new_input.maxLength = target.maxLength;
                }
                new_input.className = target.className;
                new_input.value = target.value;
                target.insertAdjacentElement("beforebegin", new_input);
                target.remove();
            }, true);
        });
    };
})();