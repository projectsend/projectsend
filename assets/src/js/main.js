(function () {
    'use strict';

    $(document).ready(function() {
        admin.parts.main();
        admin.parts.misc();
        admin.parts.mainMenuSidebar();
        admin.parts.bulkActions();
        admin.parts.jqueryValidationCustomMethods();
        admin.parts.passwordVisibilityToggle();
        admin.parts.loadCKEditor();
        admin.parts.downloadCookieHandler();
        admin.parts.select2();
        admin.parts.publicLinksPopup();
        admin.parts.generatePassword();

        // Switch pages
        switch ($("body").data("page-id")) {
            case 'install':
                admin.pages.install();
                break;
            case 'login':
                admin.pages.loginForm();
                admin.pages.loginLdapForm();
                admin.parts.login2faInputs();
                break;
            case 'dashboard':
                admin.pages.dashboard();
                admin.parts.widgetStatistics();
                admin.parts.widgetActionLog();
                admin.parts.widgetNews();
                break;
            case 'categories_list':
                admin.pages.categoriesAdmin();
                break;
            case 'clients_memberships_requests':
                admin.pages.clientsAccountsRequests();
                break;
            case 'clients_accounts_requests':
                admin.pages.clientsAccountsRequests();
                break;
            case 'file_editor':
                admin.pages.fileEditor();
                break;
            case 'client_form':
                admin.pages.clientForm();
                break;
            case 'user_form':
                admin.pages.userForm();
                break;
            case 'group_form':
                admin.pages.groupForm();
                break;
            case 'email_templates':
                admin.pages.emailTemplates();
                break;
            case 'default_template':
            case 'manage_files':
                admin.parts.filePreviewModal();
                admin.parts.foldersAdmin();
                break;
            case 'manage_downloads':
                admin.parts.filePreviewModal();
                break;
            case 'reset_password_enter_email':
                admin.pages.resetPasswordEnterEmail();
                break;
            case 'reset_password_enter_new':
                admin.pages.resetPasswordEnterNew();
                break;
            case 'upload_form':
                admin.pages.uploadForm();
                break;
            case 'import_orphans':
                admin.pages.importOrphans();
                break;
            case 'options':
                admin.pages.options();
                break;
            case 'asset_editor':
                admin.pages.assetEditor();
                break;
            case 'public_files_list':
                admin.parts.filePreviewModal();
                admin.pages.publicFilesList();
                break;
            case 'public_download':
                admin.parts.filePreviewModal();
                break;
            default:
                // do nothing
                break;
        }
    });
})();