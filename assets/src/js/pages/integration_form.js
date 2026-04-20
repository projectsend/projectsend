(function () {
    'use strict';

    admin.pages.integration_form = function () {
        // For integrations-add.php
        const typeSelect = document.getElementById('type');
        if (typeSelect) {
            const credentialsContainer = document.getElementById('credentials_fields');
            const integrationInfos = document.querySelectorAll('.integration-type-info');
            const defaultInfo = document.getElementById('default_info');

            // Available types configuration - will be populated by PHP
            const availableTypesElement = document.getElementById('integration_form');
            const availableTypes = availableTypesElement ? JSON.parse(availableTypesElement.dataset.availableTypes) : {};

            function updateCredentialFields() {
                const selectedType = typeSelect.value;
                credentialsContainer.innerHTML = '';

                // Hide all integration info panels
                integrationInfos.forEach(info => info.style.display = 'none');

                if (selectedType && availableTypes[selectedType]) {
                    const typeConfig = availableTypes[selectedType];

                    // Show relevant integration info
                    const typeInfo = document.querySelector(`[data-type="${selectedType}"]`);
                    if (typeInfo) {
                        typeInfo.style.display = 'block';
                        defaultInfo.style.display = 'none';
                    }

                    // Don't create fields for coming soon types
                    if (typeConfig.coming_soon) {
                        return;
                    }

                    if (typeConfig.fields) {
                        // Create header
                        const header = document.createElement('div');
                        header.className = 'mb-4';
                        header.innerHTML = `
                            <h5>${typeConfig.name} Configuration</h5>
                            <hr>
                        `;
                        credentialsContainer.appendChild(header);

                        // Create fields
                        Object.keys(typeConfig.fields).forEach(fieldName => {
                            const fieldConfig = typeConfig.fields[fieldName];
                            const fieldContainer = document.createElement('div');
                            fieldContainer.className = 'mb-3';
                            let fieldHtml = '';
                            const fieldId = `credentials_${fieldName}`;
                            const fieldNameAttr = `credentials[${fieldName}]`;
                            const isRequired = fieldConfig.required ? 'required' : '';
                            const requiredMark = fieldConfig.required ? ' *' : '';
                            const placeholder = fieldConfig.placeholder ? `placeholder="${fieldConfig.placeholder}"` : '';
                            const helpText = fieldConfig.help ? `<div class="form-text">${fieldConfig.help}</div>` : '';

                            if (fieldConfig.type === 'select' && fieldConfig.options) {
                                fieldHtml = `
                                    <label for="${fieldId}" class="form-label">${fieldConfig.label}${requiredMark}</label>
                                    <select name="${fieldNameAttr}" id="${fieldId}" class="form-select" ${isRequired}>
                                        <option value="">Choose...</option>
                                `;
                                Object.keys(fieldConfig.options).forEach(optionValue => {
                                    fieldHtml += `<option value="${optionValue}">${fieldConfig.options[optionValue]}</option>`;
                                });
                                fieldHtml += `</select>${helpText}`;
                            } else if (fieldConfig.type === 'checkbox') {
                                const defaultChecked = fieldConfig.default ? 'checked' : '';
                                fieldHtml = `
                                    <div class="form-check">
                                        <input type="checkbox" name="${fieldNameAttr}" id="${fieldId}"
                                               class="form-check-input" value="1" ${defaultChecked}>
                                        <label for="${fieldId}" class="form-check-label">${fieldConfig.label}</label>
                                    </div>
                                    ${helpText}
                                `;
                            } else {
                                const inputType = fieldConfig.type === 'password' ? 'password' : 'text';
                                fieldHtml = `
                                    <label for="${fieldId}" class="form-label">${fieldConfig.label}${requiredMark}</label>
                                    <input type="${inputType}" name="${fieldNameAttr}" id="${fieldId}"
                                           class="form-control" ${isRequired} ${placeholder}>
                                    ${helpText}
                                `;
                            }

                            fieldContainer.innerHTML = fieldHtml;
                            credentialsContainer.appendChild(fieldContainer);
                        });
                    }
                } else {
                    // Show default info when no type selected
                    if (defaultInfo) {
                        defaultInfo.style.display = 'block';
                    }
                }
            }

            // Update fields when type changes
            typeSelect.addEventListener('change', updateCredentialFields);

            // Initialize on page load
            updateCredentialFields();
        }

        // For integrations-edit.php
        const updateCredentialsCheckbox = document.getElementById('update_credentials');
        const credentialsSection = document.getElementById('credentials_section');

        if (updateCredentialsCheckbox && credentialsSection) {
            updateCredentialsCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    credentialsSection.style.display = 'block';
                } else {
                    credentialsSection.style.display = 'none';
                }
            });
        }
    };
})();