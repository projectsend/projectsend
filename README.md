# ProjectSend

![ProjectSend logo](https://www.projectsend.org/projectsend-logo-new.png)

## About

ProjectSend is a free, clients-oriented, private file sharing web application.

Clients are created and assigned a username and a password.  
Uploaded files can be assigned to specific clients or clients groups.

Other featres include auto-expiration of upload, notifications, full logging of actions by users and clients, option to allow clients to also upload files, themes, multiple languages...

Main website: [projectsend.org](https://www.projectsend.org)  
git: [current repository](https://github.com/projectsend/projectsend/)

Feel free to participate!

## IMPORTANT

It is recommended that you download the latest release from the official website.

Downloading a development version directly from the repository might give you unexpected results, such as visible errors, functions that are still not finished, etc.

## Server requirements

Your server needs to be configured with at least:

* php 7.1 or newer
* MySQL 5.0 or newer(*)
* Apache 2.2 or NGINX
* The following php extensions enabled on php.ini
  * pdo
  * pdo_mysql
  * mbstring
  * gettext
  * fileinfo
  * gd2
  * xml

(*) If you are using version 8.x or newer, please set the authentication method of your database so it uses the MySQL native password. The default method (caching_sha2_password) will not work. Thanks to user jellevdbos for pointing this out.

If possible, make sure to have php configured with:

* memory_limit set to 128M or more

## Optional: Improve downloads by using XSendFile

If you have access to edit your Apache or NGINX modules and configurations, it's recommended that you enable XSendFile so the files are served directly, eliminating php as a middle-man, which can be the cause of slow, interrupted downloads, or corrupted files.
After enabling the module, make sure to also enable the option in the General options panel of ProjectSend.

A few resources on how to get started:

* [mod_xsendfile for Apache2/Apache2.2](https://tn123.org/mod_xsendfile/)
* [XSendfile on NGINX wiki](https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/)

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

## Developing

If you want to help with development, you will need to do a few things via the command line:

1. Download the npm and composer dependencies with the commands ````npm install```` and ````composer update````
1. Run the default gulp task simply with ````gulp```` to compile the main CSS and JS assets files.

## How to join the project

Questions, ideas?

Send your message to contact@projectsend.org or join us on our [Facebook page](https://www.facebook.com/projectsend/)

## Translations

Thanks. Arigatō. Danke. Gracias. Grazie. Mahadsanid. Salamat po. Merci. אַ דאַנק.

You can download the compiled, translated files for the available languages from [projectsend.org/translations](https://www.projectsend.org/translations/)

If you want to translate ProjectSend in your language or work on an existing translation, please join the project on [Transifex](https://www.transifex.com/projects/p/projectsend)

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
* mschop
* Everyone that commented and gave suggestions on the issues and Facebook pages!
* stackoverflow.com
* iconfinder.com