Installing SimpleSAMLphp from the repository
============================================

These are some notes about running SimpleSAMLphp from the repository.

Installing from github
----------------------

Go to the directory where you want to install SimpleSAMLphp:

    cd /var

Then do a git clone:

    git clone git@github.com:simplesamlphp/simplesamlphp.git simplesamlphp

Initialize configuration and metadata:

    cd /var/simplesamlphp
    cp -r config-templates/* config/
    cp -r metadata-templates/* metadata/

Install the external dependencies with Composer (you can refer to [getcomposer.org](http://getcomposer.org/) to get detailed
instructions on how to install Composer itself):

    php composer.phar install


Upgrading
---------

Go to the root directory of your SimpleSAMLphp installation:

    cd /var/simplesamlphp

Ask git to update to the latest version:

    git fetch origin
    git pull origin master

Install or upgrade the external dependencies with Composer ([get composer](http://getcomposer.org/)):

    php composer.phar install


Migrating from Subversion
-------------------------

If you installed SimpleSAMLphp from subversion, and want to keep updated on the development, you will have to migrate
your installation to git. First, follow the steps to get a fresh install from github in a different directory. Skip the
steps regarding configuration and metadata initialization, and copy all the files you might have modified instead (not
only configuration and metadata, but also any custom modules or templates). Finally, proceed to install Composer and
install all the dependencies with it. You may want to add all your custom files to the '.gitignore' file.

If you really want to use subversion instead of git, or it is impossible for you to migrate (you cannot install git, for
example), you might want to do a fresh install like the one described here, but using github's subversion interface.
Refer to [github's documentation](https://help.github.com/articles/support-for-subversion-clients) for detailed
instructions on how to do that.
