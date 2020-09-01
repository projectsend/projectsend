# ProjectSend

![ProjectSend logo](https://www.projectsend.org/projectsend-logo-600.png)

## About

ProjectSend is a free, clients-oriented, private file sharing web application.

Clients are created and assigned a username and a password.  
Uploaded files can be assigned to specific clients or clients groups.

Other featres include auto-expiration of upload, notifications, full logging of actions by users and clients, option to allow clients to also upload files, themes, multiple languages...

Main website: [projectsend.org](https://www.projectsend.org)  
git: [project page for the legacy line](https://github.com/projectsend/projectsend-legacy/)
~~Old repository (unused): [Google Code](http://code.google.com/p/clients-oriented-ftp)~~

Feel free to participate!

## IMPORTANT

It is recommended that you download the latest release from the official website.

Downloading a development version directly from the repository might give you unexpected results, such as visible errors, functions that are still not finished, etc.

**If you want to download directly from the repository, please be aware that you will need to follow the instructions on the "Developing" section.**

## Server requirements

Your server needs to be configured with at least:

* php 5.6 or newer
* MySQL 5.0 or newer
* apache 2.2
* The following php extensions enabled on php.ini
  * php_pdo.dll
  * php_pdo_mysql.dll

If possible, make sure to have php configured with:

* memory_limit set to 128M or more
* The following php extensions enabled:
  * php_fileinfo.dll
  * php_gd2.dll
  * php_gettext.dll
  * php_mbstring.dll
  * php_xmlrpc.dll

## How to install on your server

Preparations:

1. Download and unzip the lastest version of ProjectSend to a folder of your choice.
2. Create a new database on your server. Create/assign a user to it.

When those are steps are completed, follow this instructions:

1. Rename includes/sys.config.sample.php to sys.config.php and set your database info there.
2. Upload ProjectSend to your selected destination.
3. Open your browser and go to https://your-projectsend-folder/install
4. Complete the information there and wait for the correct installation message.

Congratulations! ProjectSend is now installed and ready for action!
You may login with your new username and password.

## How to upgrade to a newer version

1. Download your version of choice from the official project page.
2. Upload the files via FTP to your server and replace the ones of the older version.

That's it!
Your personal configuration file (sys.config.php) is never included on the downloadable versions, so it will not be replaced while upgrading.
When a system user logs in to the system version, a check for database missing data will be made, and if anything is found, it will be updated automatically and a message will appear under the menu one time only.
Whenever a new version is available, you will be notified in the admin panel via a message shown under the main menu.

## How to join the project

Questions, ideas?

Send your message to contact@projectsend.org or join us on our [Facebook page](https://www.facebook.com/projectsend/)

## Translations

Thanks. Arigatō. Danke. Gracias. Grazie. Mahadsanid. Salamat po. Merci. אַ דאַנק.

If you want to translate ProjectSend in your language or download an existing translation, please join the project on [Transifex](https://www.transifex.com/projects/p/projectsend)

## License

ProjectSend is licensed under [GNU GPL v2](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

## Change log

[Available at the official site](http://www.projectsend.org/change-log/)

## Special thanks

Many thanks to the authors and teams behind the dependencies used by ProjectSend.

Also, thank you to the following people/communities that helped during development, either by giving support, sending code, translations, etc.

* lenamtl
* Alejandro D'Ambrosio
* k.flipflip
* Diego Carreira Vidal
* Scott Wright
* Everyone that commented and gave suggestions on the issues and Facebook pages!
* stackoverflow.com
* iconfinder.com

ProjectSend original translators:

* Raúl Elenes (Spanish)
* Vašík Greif (Czech)
* Mathieu Noe (French)
* Levin Germann (German)