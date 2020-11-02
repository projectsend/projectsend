(function () {
    'use strict';

    admin.parts.jqueryValidationCustomMethods = function () {

        $(document).ready(function(){
            jQuery.validator.addMethod("alphanumericUsername", function(value, element) {
                return this.optional(element) || /^[\w.]+$/i.test(value);
            }, json_strings.validation.alpha_user);

            jQuery.validator.addMethod("passwordValidCharacters", function(value, element) {
                return this.optional(element) || /^[0-9a-zA-Z`!"?$%\^&*()_\-+={\[}\]:;@~#|<,>.'\/\\]+$/.test(value);
            }, json_strings.validation.alpha_user);
        });
    };
})();