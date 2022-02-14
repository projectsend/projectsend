# Encryption added
For security reasons there has been added encryption and decryption for email and address data.

## Config File Modifications
In the configuration file a new **DEFINE** is introduced to control if encryption is used.

```
define('ENCRYPT_PI', True||False);
```

## Database Modifications

As the length of the encrypted email is much longer than the varchar defined in the database there must be a column change:

```
alter table tbl_users modify column email text NOT NULL;
```

## Encryption Keys
For this purpose encryption keys need to be defined and added to the application either as a separate include or within **sys.config.php**.
Example of encryption keys:

```
define(('FIRSTKEY','sK7qcOKwDP20oA8GP0goV9UwIjMCUWFPAf6lHMSUUjU=');
define(('SECONDKEY','OqKx6DYPxAh6wWa+ohhUDFmHCncSDJLmo+szbJafhkz/589tfY59zDeWvoI69lOm4lhQRmrV+MRii1L3+3eV6w==');
```

These keys can be generated with the script **create_encryption_keys.php** in the install directory.

The output of this script is in form of a php include:

```
<?php
define('FIRSTKEY','sK7qcOKwDP20oA8GP0goV9UwIjMCUWFPAf6lHMSUUjU=');
define('SECONDKEY','OqKx6DYPxAh6wWa+ohhUDFmHCncSDJLmo+szbJafhkz/589tfY59zDeWvoI69lOm4lhQRmrV+MRii1L3+3eV6w==');
?>
```

The user can decide if he adds the **define** statements themself to the end of the **sys.config.php** file or
creates a separate file which is included in the application.

More sophisticated solutions get the encryption keys from an external api.
