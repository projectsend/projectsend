ProjectSend (previously cFTP) is a free, clients-oriented, private file
sharing web application.

Clients are created and assigned a username and a password. Then you can
upload as much files as you want under each account, and optionally add
a name and description to them. 

ProjectSend is hosted on github.

Feel free to participate!

Main website:
http://www.projectsend.org/

Project:
https://github.com/ignacionelson/ProjectSend/
Old repository http://code.google.com/p/clients-oriented-ftp/

Translations:
https://www.transifex.com/projects/p/projectsend/

--------------------------------------------------------------------------------------------

How to install on your server:

Preparations:
1. Download and unzip the lastest version of ProjectSend to a folder of your choice.
2. Create a new database on your server. Create/assign a user to it.

When those are steps are completed, follow this instructions:
1. Rename includes/sys.config.sample.php to sys.config.php and set your database info there.
2. Upload ProjectSend to your selected destination.
3. Open your browser and go to http://your-projectsend-folder/install
4. Complete the information there and wait for the correct installation message.

Congratulations! ProjectSend is now installed and ready for action!
You may login with your new username and password.

Important Note: for version r608 and later you will need to enable PDO extension from php.ini

extension=php_pdo.dll
extension=php_pdo_mysql.dll

and restart the service if your are local.

--------------------------------------------------------------------------------------------

How to upgrade to a newer version:

1. Download your version of choice from the official project page.
2. Upload the files via FTP to your server and replace the ones of the older version.

That's it!
Your personal configuration file (sys.config.php) is never included on the downloadable
versions, so it will not be replaced while upgrading.

When a system user logs in to the system version, a check for database missing data will be
made, and if anything is found, it will be updated automatically and a message will appear
under the menu one time only.

Whenever a new version is available, you will be notified in the admin panel via a message
shown under the main menu.

--------------------------------------------------------------------------------------------

Questions, ideas? Want to join the project?
Send your message to contact@projectsend.org or join us on Facebook, on
https://www.facebook.com/projectsend/

--------------------------------------------------------------------------------------------

Thanks. Arigatō. Danke. Gracias. Grazie. Mahadsanid. Salamat po. Merci. אַ דאַנק.
ProjectSend original translators:

- Raúl Elenes
  Spanish

- Vašík Greif
  Czech

- Mathieu Noe
  French

- Levin Germann
  German

If you want to translate ProjectSend in your language, join the project on
https://www.transifex.com/projects/p/projectsend/
More languages are already available there.

--------------------------------------------------------------------------------------------

Many thanks to the authors of the following scripts, which are used on ProjectSend:

- jQuery
  http://www.jquery.com/

- Bootstrap (custom download)
  http://getbootstrap.com/

- hashchange
  http://benalman.com/projects/jquery-hashchange-plugin/

- Plupload
  http://www.plupload.com/

- Timthumb
  http://code.google.com/p/timthumb/

- jQuery Tags Input
  https://github.com/xoxco/jQuery-Tags-Input

- footable
  https://github.com/bradvin/FooTable

- multiselect.js
  http://loudev.com/

- flot
  https://github.com/flot/flot

- phpmailer
  http://phpmailer.worxware.com/

--------------------------------------------------------------------------------------------

Also, thank you to the following people/communities that helped during development, either
by giving support, sending code, translations, etc.

- lenamtl
- Alejandro D'Ambrosio
- k.flipflip
- Diego Carreira Vidal
- Scott Wright
- Everyone that commented and gave suggestions on the issues and Facebook pages!

- stackoverflow.com
- iconfinder.com

I know that there are more people that deserve to be on this list. I will keep adding them
as I find their names/websites.
