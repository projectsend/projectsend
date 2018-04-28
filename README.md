
Forked from ProjectSend

## How to install on your server:

Preparations:

1. Download and unzip the lastest version of MicroHealth Send to a folder of your choice.
2. Create a new database on your server. Create/assign a user to it.

When those are steps are completed, follow this instructions:

1. Rename includes/sys.config.sample.php to sys.config.php and set your database info there.
2. Upload MicroHealth Send to your selected destination.
3. Open your browser and go to http://your-projectsend-folder/install
4. Complete the information there and wait for the correct installation message.


enable PDO extension from php.ini

```
extension=php_pdo.dll
extension=php_pdo_mysql.dll
```

and restart the service if your are local.

Congratulations! MicroHealth Send is now installed and ready for action!
You may login with your new username and password.

## How to upgrade to a newer version:

1. Download your version of choice from the official project page.
2. Upload the files via FTP to your server and replace the ones of the older version.

That's it!
Your personal configuration file (sys.config.php) is never included on the downloadable versions, so it will not be replaced while upgrading.
When a system user logs in to the system version, a check for database missing data will be made, and if anything is found, it will be updated automatically and a message will appear under the menu one time only.
Whenever a new version is available, you will be notified in the admin panel via a message shown under the main menu.

