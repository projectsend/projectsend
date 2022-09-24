(function () {
'use strict';

    admin.parts.login2faInputs = function () {
        $(document).ready(function () {
            // Adapted from an answer at https://stackoverflow.com/questions/71691128/how-to-make-a-otp-based-input-design-with-one-input-field
            // @todo
            // - Also consider numeric keypad
            // - Paste entire code from the first input
            const inputs = document.querySelectorAll('#otp_inputs > *[id]');
            for (let i = 0; i < inputs.length; i++) {
                inputs[i].addEventListener('keydown', function (event) {
                    if (event.key === "Backspace") {
                        inputs[i].value = '';
                        if (i !== 0) inputs[i - 1].focus();
                    } else {
                        if (i === inputs.length - 1 && inputs[i].value !== '') {
                            return true;
                        } else if (event.keyCode > 47 && event.keyCode < 58) {
                            inputs[i].value = event.key;
                            if (i !== inputs.length - 1) inputs[i + 1].focus();
                            event.preventDefault();
                        } else if (event.keyCode > 64 && event.keyCode < 91) {
                            inputs[i].value = String.fromCharCode(event.keyCode);
                            if (i !== inputs.length - 1) inputs[i + 1].focus();
                            event.preventDefault();
                        }
                    }
                });
            }
        });
    };
})();
