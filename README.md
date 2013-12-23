# Yottaa eComet Plugin for WordPress (Beta)

Yottaa Site Optimizer will speed up your WordPress website automatically.  Faster sites have lower bounce rate, higher conversion rate, and more sales.

Whether you're already a Yottaa Site Optimizer user or want to try it for the first time, you'll enjoy the ease of having a Yottaa control panel right on your WordPress admin panel. Plugin users also have access to special caching features only available through the WordPress eComet plugin, which can improve page speed even beyond Yottaa Site Optimizer's standard techniques.

## Plugin Setup ##

[Setup Guide](http://www.yottaa.com/)

## Build Plugin ##

1. Install Ant

    Install and add [required jars](http://ant.apache.org/manual/Tasks/scp.html) for scp task.

2. Build Yottaa Module

    Once you clone the repository, run following ant command to build the Yottaa module

    ```
    ant package
    ```

    You can then install the module with the generated zip files under the build directory.

3. Setup Dev Environment for Yottaa Module

    If you have local or remote installation of WordPress and you want update the module constantly, you can add a custom-build.properties file right under the root directory of your copy of github project.

    Put following configurations in the properties file and replace the values with your own settings

    ```
    wordpress.location=[Root directory of your local WordPress installation]
    scp.wordpress.host=[Server IP for your remote WordPress installation]
    scp.wordpress.username=[Username for accessing your server]
    scp.wordpress.password=[Password for accessing your server]
    scp.wordpress.basepath=[Root directory of your remote WordPress installation]
    ```

    You can then run

    ```
    ant dev
    ```
    to update your local WordPress installation.

    or

    ```
    ant publish
    ```
    to update your remote WordPress installation.

## Install Plugin ##

1. Download [wp-yottaa.zip](https://github.com/Yottaa/e-comet-wordpress/blob/master/dist/wp-yottaa.zip?raw=true).

2. Unzip it onto the [Directory of your WordPress installation]/wp-content/plugins directory.

3. Find wp-config.php, which is under the [Directory of your WordPress installation], and add the following lines:

   ```
    $temp_ip = explode(',', isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : $_SERVER['REMOTE_ADDR']));
    $remote_addr = trim($temp_ip[0]);
    $_SERVER['REMOTE_ADDR'] = preg_replace('/[^0-9.:]/', '', $remote_addr );
   ```

4. If you want to enable logging for WordPress, comment out the line that disables logging and add following lines to the wp-config.php

   ```
    //define('WP_DEBUG', false);
    // Enable WP_DEBUG mode
    define('WP_DEBUG', true);
    // Enable Debug logging to the /wp-content/debug.log file
    define('WP_DEBUG_LOG', true);
    // Disable display of errors and warnings
    define('WP_DEBUG_DISPLAY', false);
    @ini_set('display_errors',0);
   ```

5. Log into your WordPress admin panel. Click on "Plugins" link of the left side menu. Select and activate Yottaa plugin from the list.

6. Now click on "Settings" link of the left side menu and then click on "Yottaa". This will bring you to the setting page for the Yottaa plugin.

   1. If you are creating a new Yottaa account, fill out the the form shown in the image below. (Some of the fields should be pre-populated, but fill out any blank ones). Once that information is saved your Yottaa account will be created, but will be in "Preview" mode. You can then either click the "Yottaa Dashboard" link or go to Yottaa and log in.  There, follow the steps to activate Yottaa. Once you activate, you'll have a free 7 day trial, but this will become your two month free service once you email sales@yottaa.com

   2. If you are adding an existing Yottaa account, click the tab for "Exisiting Yottaa Account" and you'll see a form to fill your Site ID, API Key, and User ID.

   3. To find these, open a new tab and log into [Yottaa](http://apps.yottaa.com). Once logged in you will see a string of letters and numbers in the URL.  This is your site ID. Next, in the left navigation click "Settings" and "API Key". This page will display both your API Key and User ID. Paste these three numbers into the fields on the WordPress configuration panel.

   4. Scroll down the Yottaa settings page . At the bottom there is a checklist of three items. To complete these, return to [Yottaa](http://apps.yottaa.com).

   5. Click the Site Optimizer tab, and click CDN and Cache Control in the left navigation. Set the recommended setting listed in the Yottaa Optimization Settings section of this page.

   6. Return to the WordPress eComet plugin and confirm that the site is listed as "Live" and checklist items have changed to "Passed".  Here you can also make sure the box is checked for "Automatically clear Yottaa's Site Optimizer cache on node changes. (This will automatically send the most updated version of your site to Yottaa's CDN nodes, ensuring that your visitors have the fastest load times possible).

![alt text][plugin]

[plugin]: https://raw.github.com/Yottaa/e-comet-wordpress/master/docs/images/1.png?login=yong-qu-yottaa&token=f1a119d6b754c3d1ef545c2708d6972f "Plugin screen shot"

## Yottaa Optimization Settings ##

1. Enable Asset Caching with all default settings.

2. Enable HTML Caching with following settings

   1. HTML Caching settings:

      Default caching behavior - follow HTTP cache control header when possible

   2. Additional Settings:

      If request URL contains query string: Unique cache

   3. Exceptions to HTML Caching:

      If URI matches RegExp "(.*)"
      and Request-Header whose name equals to "Cookie" and whose value contains "wordpress_logged_in"
      Then exclude this resource from optimization.
      If URI contains "/wp-admin"
      Then exclude this resource from optimization.

![alt text][settings]

[settings]: https://raw.github.com/Yottaa/e-comet-wordpress/master/docs/images/4.png?login=yong-qu-yottaa&token=b300c8dda195e4b163e2f144cdc93c5c "Settings screen shot"

## Plugin Settings ##

1. Automatically clear Yottaa's site optimizer cache on post/comment changes.

    If it is checked, the plugin will purge related Yottaa cache items when a post or comment is updated or deleted.

2. Clear Yottaa's site optimizer cache for navigation pages.

    If it is checked, the plugin will purge Yottaa cache items related to the navigation pages.

3. Clear Yottaa's site optimizer cache for comment navigation pages.

    If it is checked, the plugin will purge Yottaa cache items related to the navigation pages of the updated comment.

4. Enable logging for Yottaa service calls.

    If it is checked, the plugin will log request/response details of Yottaa service calls that it makes.
    To enable Wordpress logging, comment out the line that disables logging and add following lines to the wp-config.php

## Links ##

* [Yottaa](http://www.yottaa.com)
* [WordPress](http://www.wordpress.org/)
