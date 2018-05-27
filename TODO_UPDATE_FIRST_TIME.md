### The proposed structure changes from this branch will need automation for the following task during updates

- Move the sys.config.php file from includes/ to config/. This probably needs to be executed regularly until providers adapt their own routines to comply with the new directory structure (eg: Softaculous installer)
- Move the branding logo image uploaded by the user from /img/custom/logo to /upload/branding
- Clean up the directory by removing the files from the old branch
