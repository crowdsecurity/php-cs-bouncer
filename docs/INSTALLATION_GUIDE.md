![CrowdSec Logo](images/logo_crowdsec.png)

# CrowdSec Bouncer PHP library

## Installation Guide


<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Requirements](#requirements)
- [Installation](#installation)
- [Standalone mode installation](#standalone-mode-installation)
  - [Files permission](#files-permission)
  - [Settings file](#settings-file)
  - [`auto_prepend_file` directive](#auto_prepend_file-directive)
    - [`.ini` file](#ini-file)
    - [Nginx](#nginx)
    - [Apache](#apache)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->


## Requirements

- PHP >= 7.2

## Installation

Use `Composer` by simply adding `crowdsec/bouncer` as a dependency:

    composer require crowdsec/bouncer


## Standalone mode installation

This library can also be used on its own so that every browser access to a php script will be bounced.

In order to use the standalone mode, you will have to :

- give the correct permission for the folder that contains the lib

- copy the `scripts/auto-prepend/settings.example.php` to a `scripts/auto-prepend/settings.php` file

- set an `auto_prepend_file` directive in your PHP setup.


### Files permission

The owner of the `/path/to/the/crowdsec-lib` should be your webserver owner (e.g. `www-data`).

You can achieve it by running command like:

```
sudo chown www-data /path/to/the/crowdsec-lib
```

### Settings file

Please copy the `scripts/auto-prepend/settings.example.php` to a `scripts/auto-prepend/settings.php`
and fill the necessary settings in it (see [Configurations settings](./USER_GUIDE.md/#configurations) for more details).

### `auto_prepend_file` directive

We will now describe how to set an `auto_prepend_file` directive in order to call the `scripts/auto-prepend/bounce.php` for each php script access.

Adding an `auto_prepend_file` directive can be done in different ways:

#### `.ini` file

You should add this line to a `.ini` file :

    auto_prepend_file = /absolute/path/to/scripts/auto-prepend/bounce.php

#### Nginx

If you are using Nginx, you should modify your nginx configuration file by adding a `fastcgi_param`
directive. The php block should look like below:

```
location ~ \.php$ {
    ...
    ...
    ...
    fastcgi_param PHP_VALUE "/absolute/path/to/scripts/auto-prepend/bounce.php";
}
```

#### Apache

If you are using Apache, you should add this line to your `.htaccess` file:

    php_value auto_prepend_file "/absolute/path/to/scripts/auto-prepend/bounce.php"

