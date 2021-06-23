# Standalone mode

There is many technical ways to bounce users.

In this quick guide, you will learn how to bounce **without modifying the existing code** by running the bouncing behavior just before running the project code. For this, there is an example showing how to do it using the PHP setting `auto_prepend_file`. We will test it now.

## Step 1: Create the settings.php file

Start from the exising `settings.example.php`:

```bash
cd path/to/this/lib
cp examples/auto-prepend/settings.example.php examples/auto-prepend/settings.php
```

> Note: don't forget to replace the values according to your needs in `examples/auto-prepend/settings.php`.

## Step 2: start the "auto_prepend_file" mechanism

Considering you're using Apache, modify (or add) the main `.htaccess` file at the root of your project, adding this single line to the top:

```apacheconf
php_value auto_prepend_file "./path/to/this/lib/examples/auto-prepend/script/bounce-via-autoprepend.php"
```

> Remember to **adapt the path to yours**. You can use absolute or relative.

> **Not using apache?** If you using _NGINX_ or an other webserver, the way to modify the _PHP_ flag is different but still possible (ndlr: add a example).

This will run the `bounce-via-autoprepend.php` script each time before running the main php script. If the IP has to be bounce, the script will show the wall (ban or captcha) and stop execution of the main script.

## Step 3: Test the standalone bouncer

Now you can a decision to ban your own IP for 5 minutes to test the correct behavior:

```bash
cscli decisions add --ip <YOUR_IP> --duration 5m --type ban
```

You can also test a captcha:

```bash
cscli decisions delete --all # be careful with this command!
cscli decisions add --ip <YOUR_IP> --duration 15m --type captcha
```

> Well done! Feel free to give some feedback using adding Github issues.
