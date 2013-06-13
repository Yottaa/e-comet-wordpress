<?php
/*
Plugin Name: Yottaa for Wordpress
Plugin URI: http://github.com/Yottaa/e-comet-wordpress
Version: 0.1
Author: <a href="http://www.yottaa.com">Yottaa Inc.</a>
Description: This plugin is intended to be installed within WordPress to provide Yottaa optimizer status info, links, action buttons to turn on/off Yottaa optimizer or perform full-flush of Yottaa caches, Yottaa setting checking etc. within WordPress admin panel. The plugin can also be configured to capture WordPress content events and purge HTML cache items for related content on-demand. It can also help user to sign up new account with Yottaa or register it with an existing Yottaa account without accessing Yottaa app console.

Copyright Yottaa
*/


class WPYottaa {

  function WPYottaa() {

    include_once (dirname( __FILE__ ) . '/yottaa-api.php');
    include_once (dirname( __FILE__ ) . '/yottaa-api-wordpress.php');

    // Localization init
    add_action('init', array($this, 'WPYottaaLocalization'));

    // Add Administration Interface
    add_action('admin_menu', array($this, 'WPYottaaAdminMenu'));

    // When posts/pages are published, edited or deleted
    add_action('edit_post', array($this, 'WPYottaaPurgePost'), 99);
    add_action('edit_post', array($this, 'WPYottaaPurgeCommonObjects'), 99);
    add_action('transition_post_status', array($this, 'WPYottaaPurgePostStatus'), 99, 3);
    add_action('transition_post_status', array($this, 'WPYottaaPurgeCommonObjectsStatus'), 99, 3);

    // When comments are made, edited or deleted
    add_action('comment_post', array($this, 'WPYottaaPurgePostComments'),99);
    add_action('edit_comment', array($this, 'WPYottaaPurgePostComments'),99);
    add_action('trashed_comment', array($this, 'WPYottaaPurgePostComments'),99);
    add_action('untrashed_comment', array($this, 'WPYottaaPurgePostComments'),99);
    add_action('deleted_comment', array($this, 'WPYottaaPurgePostComments'),99);

    // When posts or pages are deleted
    add_action('deleted_post', array($this, 'WPYottaaPurgePost'), 99);
    add_action('deleted_post', array($this, 'WPYottaaPurgeCommonObjects'), 99);

    // When xmlRPC call is made
    add_action('xmlrpc_call',array($this, 'WPYottaaPurgeAll'), 99);

    add_action('admin_enqueue_scripts', array($this, 'WPYottaaLoadCustomAdminStyle'));
  }

  function WPYottaaLoadCustomAdminStyle() {
    wp_register_style('custom_wp_admin_css',plugins_url('/style.css', __FILE__), false, '1.0.0');
    wp_enqueue_style('custom_wp_admin_css');
  }

  function WPYottaaLocalization() {
    load_plugin_textdomain('wp-yottaa', false, dirname(plugin_basename( __FILE__ ) ) . '/lang/');
  }

  //wrapper on WPYottaaPurgeCommonObjects for transition_post_status
  function WPYottaaPurgeCommonObjectsStatus($old, $new, $p) {
	  $this->WPYottaaPurgeCommonObjects($p->ID);
  }

  /**
   * @return void
   */
  function WPYottaaPurgeCommonObjects() {
    $yottaa_api = yottaa_api_wordpress();
    $path_configs = array(array("condition" => "/", "name" => "URI", "type" => "html"),
                          array("condition" => "/feed/", "name" => "URI", "type" => "html"),
                          array("condition" => "/feed/atom/", "name" => "URI", "type" => "html"),
                          array("condition" => "/category/(.*)", "name" => "URI", "operator" => "REGEX", "type" => "html"));
    // Also purges page navigation
    if ($yottaa_api->getUpdatePageNavParameter() == 1) {
      array_push($path_configs, array("condition" => "/page/(.*)", "name" => "URI", "operator" => "REGEX", "type" => "html"));
    }
    if ($yottaa_api->getEnableLoggingParameter() == 1) {
      $yottaa_api->log('Flushed Yottaa cache for common objects.');
    }
    $results =  $yottaa_api->flushPaths($path_configs);
    $yottaa_api->log(json_encode($results));
    return $results;
  }

  /**
   * @return mixed
   */
  function WPYottaaPurgeAll() {
    $yottaa_api = yottaa_api_wordpress();
    if ($yottaa_api->getEnableLoggingParameter() == 1) {
      $yottaa_api->log('Flushed all Yottaa cache.');
    }
    return $yottaa_api->flush();
  }

  /**
   * Using a URL, clear the cache
   *
   * @param $wpy_purl
   * @return void
   */
  function WPYottaaPurgeURL($wpy_purl) {
    $yottaa_api = yottaa_api_wordpress();
    if ($yottaa_api->getAutoClearCacheParameter() == 1) {
      $wpy_purl = str_replace(get_bloginfo('url'),"",$wpy_purl);
      if ($yottaa_api->getEnableLoggingParameter() == 1) {
        $yottaa_api->log('Flushed Yottaa cache mapped to URL ' . $wpy_purl . '.');
      }
      return $yottaa_api->flushPaths(array($wpy_purl));
    }
  }

  /**
   * wrapper on WPYottaaPurgePost for transition_post_status
   *
   * @param $old
   * @param $new
   * @param $p
   * @return mixed
   */
  function WPYottaaPurgePostStatus($old, $new, $p) {
      $yottaa_api = yottaa_api_wordpress();
      if ($yottaa_api->getAutoClearCacheParameter() == 1) {
        if ($yottaa_api->getEnableLoggingParameter() == 1) {
          $yottaa_api->log('Flushed Yottaa cache mapped to post with id ' . $p->ID . '.');
        }
	    return $this->WPYottaaPurgePost($p->ID);
      }
  }

  /**
   * Takes a post id (number) as an argument and generates
   * the location path to the object that will be purged based on the permalink.
   *
   * @param $wpy_postid
   * @return mixed
   */
  function WPYottaaPurgePost($wpy_postid) {
    $yottaa_api = yottaa_api_wordpress();
    if ($yottaa_api->getAutoClearCacheParameter() == 1) {
      $wpy_url = get_permalink($wpy_postid);
      $wpy_permalink = str_replace(get_bloginfo('url'),"",$wpy_url);
      if ($yottaa_api->getEnableLoggingParameter() == 1) {
        $yottaa_api->log('Flushed Yottaa cache for post with id ' . $wpy_postid . ' and url ' . $wpy_url . '.');
      }
      $results = $yottaa_api->flushPaths(array(array("condition" => $wpy_permalink, "name" => "URI", "type" => "html")));
      if ($yottaa_api->getEnableLoggingParameter() == 1) {
        $yottaa_api->log(json_encode($results));
      }
      return $results;
    }
  }

  /**
   * WPYottaaPurgePostComments - Purge all comments pages from a post
   *
   * @param $wpy_commentid
   * @return void
   */
  function WPYottaaPurgePostComments($wpy_commentid) {
    $comment = get_comment($wpy_commentid);
    $wpy_commentapproved = $comment->comment_approved;

    $yottaa_api = yottaa_api_wordpress();
    // If approved or deleting...
    if ($yottaa_api->getAutoClearCacheParameter() == 1 && ($wpy_commentapproved == 1 || $wpy_commentapproved == 'trash') ) {
       $wpy_postid = $comment->comment_post_ID;

       $path_configs = array(array("condition" => "/\\\?comments_popup=" . $wpy_postid, "name" => "URI", "operator" => "REGEX", "type" => "html"));

       // Also purges comments navigation
       if ($yottaa_api -> getUpdateCommentNavParameter() == 1) {
         array_push($path_configs, array("condition" => '/\\\?comments_popup=' . $wpy_postid . '&(.*)', "name" => "URI", "operator" => "REGEX", "type" => "html"));
       }
       if ($yottaa_api->getEnableLoggingParameter() == 1) {
         $yottaa_api->log('Flushed Yottaa cache for comment with id ' . $wpy_commentid . '.');
       }
       $results =  $yottaa_api->flushPaths($path_configs);
       if ($yottaa_api->getEnableLoggingParameter() == 1) {
         $yottaa_api->log(json_encode($results));
       }
       return $results;
    }
  }

  /**
   *
   */
  function WPYottaaAdminMenu() {
    if (!defined('VARNISH_HIDE_ADMINMENU')) {
      add_options_page(__('Yottaa Configuration','wp-yottaa'), 'Yottaa', 1, 'WPYottaa', array($this, 'WPYottaaAdmin'));
    }
  }

  /**
   *
   *
   * @param string $current
   * @return void
   */
  function WPYottaaAdminTabs( $current = 'homepage' ) {
    $yottaa_api = yottaa_api_wordpress();
    if (!$yottaa_api->isEmpty()) {
        $tabs = array( 'homepage' => 'Yottaa Info', 'advanced' => 'Advanced Configuration');
    } else {
        $tabs = array( 'homepage' => 'New Yottaa Account', 'advanced' => 'Existing Yottaa Account');
    }
    echo '<div id="icon-options-general" class="icon32"><br></div>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach( $tabs as $tab => $name ){
      $class = ( $tab == $current ) ? ' nav-tab-active' : '';
      echo "<a class='nav-tab$class' href='?page=WPYottaa&tab=$tab'>$name</a>";
    }
    echo '</h2>';
  }

  /**
   * Draw the administration interface.
   *
   * @return void
   */
  function WPYottaaAdmin() {

    global $pagenow;

    $yottaa_api = yottaa_api_wordpress();

    $msg = '';
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
       if (current_user_can('administrator')) {
          if (isset($_POST['wpyottaa_create_account'])) {
            if (!empty($_POST['yottaa_user_name']) && !empty($_POST['yottaa_user_phone']) && !empty($_POST['yottaa_user_email']) && !empty($_POST['yottaa_site_name'])) {
                $json_output = $yottaa_api->createAccount($_POST['yottaa_user_name'], $_POST['yottaa_user_email'], $_POST['yottaa_user_phone'], $_POST['yottaa_site_name']);
                if (!isset($json_output["error"])) {
                    $yottaa_api->updateParameters($json_output["api_key"],$json_output["user_id"],$json_output["site_id"]);

                    $msg = '<div class="updated"><p>' . __('New account form has been created with this', 'wp-yottaa') . ' ' . '<a href="' . $json_output["preview_url"] . '">' . __('preview url.', 'wp-yottaa') . '</a></p>';
                    $msg = $msg . '<p>' . __('Your Yottaa login information has been sent to your email address', 'wp-yottaa') . ' '. $_POST['yottaa_user_email'] . '</p></div>';
                }
                else {
                    $error = $json_output["error"];
                    $msg = '<div class="error"><p>' . __('Error received from creating Yottaa user:', 'wp-yottaa') . json_encode($error) . '</p></div>';
                }
            } else {
                $msg = '<div class="error"><p>' . __('Invalid input for creating a new account!', 'wp-yottaa') . '</p></div>';
            }
          } elseif (isset($_POST['wpyottaa_save_configuration'])) {
            if (!empty($_POST['yottaa_user_id']) && !empty($_POST['yottaa_api_key']) && !empty($_POST['yottaa_site_id'])) {
                $json_output = $yottaa_api->getRuntimeStatus($_POST['yottaa_api_key'], $_POST['yottaa_user_id'], $_POST['yottaa_site_id']);
                if (!isset($json_output["error"])) {
                    $yottaa_api->updateParameters($_POST['yottaa_api_key'],$_POST['yottaa_user_id'],$_POST['yottaa_site_id']);
                    $msg = '<div class="updated"><p>' . __('Yottaa account configuration has been saved!','wp-yottaa' ) . '</p></div>';
                } else {
                    $msg = '<div class="error"><p>' . __('Invalid input for Yottaa account configuration!','wp-yottaa' ) . '</p></div>';
                }
            } elseif (empty($_POST['yottaa_user_id']) && empty($_POST['yottaa_api_key']) && empty($_POST['yottaa_site_id'])) {
              $yottaa_api->deleteParameters();
              $msg = '<div class="updated"><p>' . __('Yottaa account configuration has been removed!','wp-yottaa' ) .'</p></div>';
            } else {
              $msg = '<div class="error"><p>' . __('Invalid input for Yottaa account configuration!','wp-yottaa' ) .'</p></div>';
            }
          } elseif (isset($_POST['wpyottaa_save_settings'])) {
             $val = empty($_POST[$yottaa_api->wpy_auto_clear_cache_optname]) ? 0 : 1;
             $yottaa_api->setAutoClearCacheParameter($val);
             $status = $val == 1 ? 'enabled' : 'disabled';
             $msg = '<div class="updated"><p>' . __('Automatically clearing Yottaa\'s site optimizer cache is ','wp-yottaa' ) . $status .'.</p>';
             $val2 = empty($_POST[$yottaa_api->wpy_update_pagenav_optname]) ? 0 : 1;
             $yottaa_api->setUpdatePageNavParameter($val2);
             $status2 = $val2 == 1 ? 'enabled' : 'disabled';
             $msg = $msg . '<p>' . __('Clearing Yottaa\'s site optimizer cache for page navigation is ','wp-yottaa' ) . $status2 .'.</p>';
             $val3 = empty($_POST[$yottaa_api->wpy_update_commentnav_optname]) ? 0 : 1;
             $yottaa_api->setUpdateCommentNavParameter($val3);
             $status3 = $val3 == 1 ? 'enabled' : 'disabled';
             $msg = $msg . '<p>' . __('Clearing Yottaa\'s site optimizer cache for comment navigation is ','wp-yottaa' ) . $status3 .'.</p>';
             $val4 = empty($_POST[$yottaa_api->wpy_enable_logging_optname]) ? 0 : 1;
             $yottaa_api->setEnableLoggingParameter($val4);
             $status4 = $val4 == 1 ? 'enabled' : 'disabled';
             $msg = $msg . '<p>' . __('Logging for Yottaa service calls is ','wp-yottaa' ) . $status4 .'.</p></div>';
          } elseif (isset($_POST['wpyottaa_clear_cache'])) {
              $json_output = $this->WPYottaaPurgeAll();

              if (!isset($json_output["error"])) {
                $msg = '<div class="updated"><p>' . __('Cache for all of your sites resources has been removed from Yottaa CDN.','wp-yottaa' ) .'</p></div>';
              }
              else {
                $error = $json_output["error"];
                $msg = '<div class="error"><p>' . __('Error received from flushing Yottaa cache:','wp-yottaa') . json_encode($error).'</p></div>';
              }
          } elseif (isset($_POST['wpyottaa_activate_account'])) {
          } elseif (isset($_POST['wpyottaa_pause_optimizations'])) {
            $json_output = $yottaa_api->bypass();
            if (!isset($json_output["error"])) {
              $msg= '<div class="updated"><p>' . __('Your Yottaa optimizer has been changed to bypass mode.','wp-yottaa' ) .'</p></div>';
            } else {
              $msg = '<div class="error"><p>' . __('Error received from pausing Yottaa optimizer:','wp-yottaa') . json_encode($json_output["error"]).'</p></div>';
            }
          } elseif (isset($_POST['wpyottaa_transparent_optimizations'])) {
            $json_output = $yottaa_api->transparent();
            if (!isset($json_output["error"])) {
              $msg= '<div class="updated"><p>' . __('Your Yottaa optimizer has been changed to transparent proxy mode.','wp-yottaa' ) .'</p></div>';
            } else {
              $msg = '<div class="error"><p>' . __('Error received from pausing Yottaa optimizer:','wp-yottaa') . json_encode($json_output["error"]).'</p></div>';
            }
          } elseif (isset($_POST['wpyottaa_resume_optimizations'])) {
            $json_output = $yottaa_api->resume();
            if (!isset($json_output["error"])) {
              $msg= '<div class="updated"><p>' . __('Your Yottaa optimizer has been resumed.','wp-yottaa' ) .'</p></div>';
            } else {
              $msg = '<div class="error"><p>' . __('Error received from resuming Yottaa optimizer:','wp-yottaa') . json_encode($json_output["error"]).'</p></div>';
            }
          }
       } else {
         $msg = '<div class="updated"><p>' . __('You do not have the privileges.','wp-yottaa' ) . '</p></div>';
       }
    }
    if ( isset ( $_GET['tab'] ) ) {
      $this->WPYottaaAdminTabs($_GET['tab']);
    } else {
      $this->WPYottaaAdminTabs('homepage');
    }
    if (!empty($msg)) {
        echo '<div class="wrap">' . $msg . '</div>';
    }

    $parameters = $yottaa_api->getParameters();
    $api_key = $parameters["api_key"];
    $user_id = $parameters["user_id"];
    $site_id = $parameters["site_id"];
    ?>

    <div class="wrap">
      <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
        <?php
        if ( $pagenow == 'options-general.php' && $_GET['page'] == 'WPYottaa' ){

          $tab = isset($_GET['tab']) ? $_GET['tab'] : 'homepage';

           switch ( $tab ){
              case 'advanced' :
        ?>
                 <table class="form-table">
                 <tr>
                    <th scope="row"><label for="yottaa_user_id">User Id</label></th>
                    <td>
                       <input type="text" id="yottaa_user_id" name="yottaa_user_id" class="regular-text" size="60" value="<?php echo isset($_POST['yottaa_user_id']) ? $_POST['yottaa_user_id'] : $user_id; ?>">
                       <p class="description">
                           <?php echo __('Enter your Yottaa user id.','wp-yottaa' ); ?>
                       </p>
                    </td>
                 </tr>
                 <tr>
                    <th scope="row"><label for="yottaa_api_key">API Key</label></th>
                    <td>
                       <input type="text" id="yottaa_api_key" name="yottaa_api_key" class="regular-text" size="60" value="<?php echo isset($_POST['yottaa_api_key']) ? $_POST['yottaa_api_key'] : $api_key; ?>">
                       <p class="description">
                           <?php echo __('Enter your Yottaa API key.','wp-yottaa' ); ?>
                       </p>
                    </td>
                 </tr>
                 <tr>
                    <th scope="row"><label for="yottaa_site_id">Site Id</label></th>
                    <td>
                       <input type="text" id="yottaa_site_id" name="yottaa_site_id" class="regular-text" size="60" value="<?php echo isset($_POST['yottaa_site_id']) ? $_POST['yottaa_site_id'] : $site_id; ?>">
                       <p class="description">
                           <?php echo __('Enter your Yottaa site id.','wp-yottaa' ); ?>
                       </p>
                    </td>
                 </tr>
                 <tr>
                   <td rowspan="2">
                     <p class="submit" style="clear: both;">
                       <input type="submit" name="wpyottaa_save_configuration"  class="button-primary" value="Save Configuration" />
                     </p>
                   </td>
                 </tr>
              </table>
        <?php
              break;
              case 'homepage' :
                  global $current_user;
                  get_currentuserinfo();
                  if (empty($user_id) || empty($api_key) || empty($site_id)) {
        ?>
                 <table class="form-table">
                 <tr>
                    <th scope="row"><label for="yottaa_user_name">User Name</label></th>
                    <td>
                       <input type="text" id="yottaa_user_name" name="yottaa_user_name" class="regular-text" size="60" value="<?php echo isset($_POST['yottaa_user_name']) ? $_POST['yottaa_user_name'] : $current_user->user_login; ?>">
                       <p class="description">
                           <?php echo __('Enter the full user name for your new Yottaa account.','wp-yottaa' ); ?>
                       </p>
                    </td>
                 </tr>
                 <tr>
                    <th scope="row"><label for="yottaa_user_phone">Phone</label></th>
                    <td>
                       <input type="text" id="yottaa_user_phone" name="yottaa_user_phone" class="regular-text" size="60" value="<?php echo isset($_POST['yottaa_user_phone']) ? $_POST['yottaa_user_phone'] : ''; ?>">
                       <p class="description">
                           <?php echo __('Enter the phone number for your new Yottaa account.','wp-yottaa' ); ?>
                       </p>
                    </td>
                 </tr>
                 <tr>
                    <th scope="row"><label for="yottaa_user_email">Email</label></th>
                    <td>
                       <input type="text" id="yottaa_user_email" name="yottaa_user_email" class="regular-text" size="60" value="<?php echo isset($_POST['yottaa_user_email']) ? $_POST['yottaa_user_email'] : $current_user->user_email; ?>">
                       <p class="description">
                           <?php echo __('Enter the email for your new Yottaa account.','wp-yottaa' ); ?>
                       </p>
                    </td>
                 </tr>
                 <tr>
                    <th scope="row"><label for="yottaa_site_name">Site Name</label></th>
                    <td>
                       <input type="text" id="yottaa_site_name" name="yottaa_site_name" class="regular-text" size="60" value="<?php echo isset($_POST['yottaa_site_name']) ? $_POST['yottaa_site_name'] : $_SERVER['SERVER_NAME']; ?>">
                       <p class="description">
                           <?php echo __('Enter the site name for your new Yottaa account.','wp-yottaa' ); ?>
                       </p>
                    </td>
                 </tr>
                 <tr>
                   <td rowspan="2">
                     <p class="submit" style="clear: both;">
                       <input type="submit" name="wpyottaa_create_account"  class="button-primary" value="Create Account" />
                     </p>
                   </td>
                 </tr>
                 </table>
                 <?php
                } else {
                      $json_output = $yottaa_api->getRuntimeStatus($api_key, $user_id, $site_id);
                      $yottaa_status = $json_output["optimizer"];

                      echo '<h2>' . __('My Yottaa Page','wp-yottaa') . '<img src="'. plugins_url('/images/yottaa.png', __FILE__) . '" class="yottaa-logo"></h2>';

                      if (!isset($json_output["error"])) {
                          $yottaa_preview_url = $json_output["preview_url"];
                          if ($yottaa_status == 'preview') {
                              echo '<div>Your site is currently in <span class="attention">' . $yottaa_status . '</span>.<br/> This allows you to access an optimized'
                                               . ' version of your website using the <a href="' . $yottaa_preview_url . '" target="_blank">preview URL</a>.'
                                               . '<br/>Before making your site live look over the links and configuration below.</div>';
                          }
                          elseif ($yottaa_api->isLive($yottaa_status)) {
                              echo '<div>Your site is currently in <span class="live">Live</span>.</div>';
                          }
                          elseif ($yottaa_api->isBypass($yottaa_status)) {
                              echo '<div>Your site is currently in <span class="paused">Bypass mode</span>.</div>';
                          }
                          elseif ($yottaa_api->isTransparent($yottaa_status)) {
                              echo '<div>Your site is currently in <span class="paused">Transparent Proxy mode</span>.</div>';
                          }
                      }
                      else {
                          $error = $json_output["error"];
                          echo '<div class="status-error">Error: ' . json_encode($error) . '</div>';
                      }

                      echo '<h3>Links</h3>';
                      echo '<div><a href="https://apps.yottaa.com/" target="_blank">Yottaa Dashboard</a></div>';
                      echo '<div><a href="https://apps.yottaa.com/framework/web/sites/' . $site_id . '/optimizer" target="_blank">Yottaa Site Overview</a></div>';
                      echo '<div><a href="https://apps.yottaa.com/framework/web/sites/' . $site_id . '/settings" target="_blank">Yottaa Optimization Configuration</a></div>';

                      echo '<h3>Actions</h3><h4>Switch Optimizer Mode</h4>';
                      if (!isset($json_output["error"])) {
                        if ($yottaa_status == 'preview') {
                          echo '<p><a href="https://apps.yottaa.com/framework/web/sites/' . $site_id . '" class="button-primary button-mode" target="_blank">' . __('Activate Account','wp-yottaa') . '</a></p>';
                          echo '<p>Activating your site allows all e-commerce visitors to receive the benefits out Yottaa\'s site speed optimizer.</p>';
                        }
                        elseif ($yottaa_api->isLive($yottaa_status)) {
                          echo '<p><input type="submit" name="wpyottaa_pause_optimizations"  class="button-primary button-mode" value="Bypass Mode" /></p>';
                          echo '<p>In Bypass Mode, Yottaa DNS will route all traffic to your origin server directly, by-passing optimizations, SSL termination and origin server shielding etc.</p>';
                          echo '<p>Your origin servers IP address are visible to anyone in this mode.</p>';
                          echo '<p><input type="submit" name="wpyottaa_transparent_optimizations"  class="button-primary button-mode" value="Transparent Proxy" /></p>';
                          echo '<p>In Transparent Proxy Mode, Yottaa will proxy your site traffic through the Yottaa Network without applying any optimization. Other features such as SSL termination, site protection and Private Test are in effect as usual.</p>';
                          echo '<p>Your origin servers IP address are shielded by Yottaa.</p>';
                        }
                        elseif ($yottaa_api->isPaused($yottaa_status)) {
                          echo '<p><input type="submit" name="wpyottaa_resume_optimizations"  class="button-primary button-mode" value="Resume Optimizer" /></p>';
                          echo '<p>Starting optimizer will apply optimizations on your website within 5 minutes.</p>';
                        }
                        echo '<h4>Other Actions</h4>';
                        echo '<p><input type="submit" name="wpyottaa_clear_cache"  class="button-primary button-mode" value="Clear Cache" /></p>';
                        echo '<p>Clearing the cache will remove all of your sites resources from our CDN. Use this option if you have updated a resource (gif, css, JavaScript).</p>';
                      }

                      echo '<h3>Settings</h3>';
                      echo '<table>';
                      $wpy_auto_clear_cache_optval = $yottaa_api->getAutoClearCacheParameter();
                      $checked = $wpy_auto_clear_cache_optval == 1 ? 'checked="checked"' : '';
                      echo '<tr><td><input type="checkbox" name="' . $yottaa_api->wpy_auto_clear_cache_optname .'" value="1" '. $checked .'>  '. __('Automatically clear Yottaa\'s site optimizer cache on post/comment changes.','wp-yottaa')  . '</td></tr>';
                      $wpy_update_pagenav_optval = $yottaa_api->getUpdatePageNavParameter();
                      $checked2 = $wpy_update_pagenav_optval == 1 ? 'checked="checked"' : '';
                      echo '<tr><td><input type="checkbox" name="' . $yottaa_api->wpy_update_pagenav_optname .'" value="1" '. $checked2 .'>  '. __('Clear Yottaa\'s site optimizer cache for navigation pages.','wp-yottaa')  . '</td></tr>';
                      $wpy_update_commentnav_optval = $yottaa_api->getUpdateCommentNavParameter();
                      $checked3 = $wpy_update_commentnav_optval == 1 ? 'checked="checked"' : '';
                      echo '<tr><td><input type="checkbox" name="' . $yottaa_api->wpy_update_commentnav_optname .'" value="1" '. $checked3 .'>  '. __('Clear Yottaa\'s site optimizer cache for comment navigation pages.','wp-yottaa')  . '</td></tr>';
                      $wpy_enable_logging_optval = $yottaa_api->getEnableLoggingParameter();
                      $checked4 = $wpy_enable_logging_optval == 1 ? 'checked="checked"' : '';
                      echo '<tr><td><input type="checkbox" name="' . $yottaa_api->wpy_enable_logging_optname .'" value="1" '. $checked4 .'>  '. __('Enable logging for Yottaa service calls.','wp-yottaa')  . '</td></tr>';
                      echo '<tr><td><p><input type="submit" name="wpyottaa_save_settings"  class="button-primary" value="Save Settings" /></p></td></tr>';
                      echo '</table>';
                      echo '<table>';
                      echo '<tr><td><span class="row-title">User Id</span></td><td>' . $user_id . '</td></tr>';
                      echo '<tr><td><span class="row-title">API Key</span></td><td>' . $api_key . '</td></tr>';
                      echo '<tr><td><span class="row-title">Site Id</span></td><td>' . $site_id . '</td></tr>';
                      echo '<tr><td rowspan="2"><a href="?page=WPYottaa&tab=advanced">Advanced Configuration</a></td></tr>';
                      echo '</table>';

                      echo '<h3>Checklist</h3>';
                      $yottaa_settings = $yottaa_api->getSettings();
                      echo '<table>';
                      echo '<tr><td><span class="row-title">Enable html page caching</span></td><td>';
                      if ($yottaa_settings['site_pages_caching'] == 'included') {
                        echo '<span class="passed">Passed</span>';
                      } else {
                        echo '<span class="failed">Failed</span>';
                      }
                      echo '</td></tr>';
                      echo '<tr><td><span class="row-title">Only cache pages for anonymous users</span></td><td>';
                      if ($yottaa_settings['only_cache_anonymous_users'] == 'excluded') {
                        echo '<span class="passed">Passed</span>';
                      } else {
                        echo '<span class="failed">Failed</span>';
                      }
                      echo '</td></tr>';
                      echo '<tr><td><span class="row-title">Exclude admin pages from caching</span></td><td>';
                      if ($yottaa_settings['admin_pages_caching'] == 'excluded') {
                        echo '<span class="passed">Passed</span>';
                      } else {
                        echo '<span class="failed">Failed</span>';
                      }
                      echo '</td></tr>';
                      echo '</table>';
                }
             break;
           }
        }
        ?>
     </form>
    </div>
  <?php
  }

}

$wpyottaa = new WPYottaa();
