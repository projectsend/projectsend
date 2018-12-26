# ProjectSend

![ProjectSend logo](https://www.projectsend.org/projectsend-logo-new.png)

## ATTENTION

If you want to use ProjectSend, download the latest version from the official site.
The source code of that version is on the new [Legacy repository](https://github.com/projectsend/projectsend-legacy/).

Currently ProjectSend is under heavy refactoring.
This repository is EXTREMELY early into the process, so nothing works at the moment. Controllers, models and views need to be created, and every existing function from the autoloaded files and current classes need to be rewritten.

## About

ProjectSend is a free, clients-oriented, private file sharing web application.

Clients are created and assigned a username and a password.
Uploaded files can be assigned to specific clients or clients groups.

Other features include auto-expiration of upload, notifications, full logging of actions by users and clients, option to allow clients to also upload files, themes, multiple languages...

Main website: [projectsend.org](https://www.projectsend.org)
git: [project page](https://github.com/projectsend/projectsend)

Feel free to participate!

## Server requirements

Your server needs to be configured with at least:

* php 5.6 or newer (7.1 recommended)
* MySQL 5.0 or newer
* apache 2.2
* The following php extensions enabled on php.ini
  * php_pdo.dll
  * php_pdo_mysql.dll
  * php_xmlrpc.dll

If possible, make sure to have php configured with:

* memory_limit set to 128M or more
* The following php extensions enabled:
  * php_gd2.dll
  * php_gettext.dll
  * php_mbstring.dll
  * php_fileinfo.dll

## How to install on your server

1. Download and unzip the latest version of ProjectSend to a folder of your choice.
1. Create a new database on your server. Create and assign a user to it.
1. Rename config/config.sample.php to config.php and set your database information and desired configuration on the available constants.
1. Upload ProjectSend to your selected destination.
1. Create a virtual host on Apache and set the DocumentRoot to /path/to/projectsend/public
1. Open your browser and go to https://your-projectsend-folder/install
1. Complete the information there and wait for the correct installation message.

Congratulations! ProjectSend is now installed and ready for action!
You may login with your new username and password.

## How to upgrade to a newer version

1. Download your version of choice from the official project page.
1. Upload the files via FTP to your server and replace the ones of the older version.

That's it!
Your personal configuration file (config.php) is never included on the downloadable versions, so it will not be replaced while upgrading.
When a system user logs in to the system version, a check for database missing data will be made, and if anything is found, it will be updated automatically and a message will appear under the menu one time only.
Whenever a new version is available, you will be notified in the admin panel via a message shown under the main menu.

## Developing

### Notice: ProjectSend is currently under refactoring

If you want to help with development, you will need to do a few things via Grunt:

1. Download the composer, bower and npm dependencies. You can use the ````Grunt dependencies_update```` command which should take care of that.
1. Run the default grunt task simply with ````Grunt```` to compile the main CSS and JS assets files.

## How to join the project

Questions, ideas?
Send your message to contact@projectsend.org or join us on [Facebook](https://www.facebook.com/projectsend/)

## Translations

Thanks. Arigatō. Danke. Gracias. Grazie. Mahadsanid. Salamat po. Merci. אַ דאַנק.

If you want to translate ProjectSend in your language or download an existing translation, please join the project on [Transifex](https://www.transifex.com/projects/p/projectsend)

## License

ProjectSend is licensed under [GNU GPL v3](https://www.gnu.org/licenses/gpl.html)

## Change log

[Available at the official site](https://www.projectsend.org/change-log/)

## Special thanks

Also, thank you to the following people/communities that helped during development, either by giving support, sending code, translations, etc.

* lenamtl
* Alejandro D'Ambrosio
* k.flipflip
* Diego Carreira Vidal
* Scott Wright
* mschop
* Everyone that commented and gave suggestions on the issues and Facebook pages!
* stackoverflow.com

ProjectSend original translators:

* Vašík Greif (Czech)
* Raúl Elenes (Spanish)
* Mathieu Noe (French)
* Levin Germann (German)