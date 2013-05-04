<?php

/**
 * Yottaa Wordpress API Class.
 */

class YottaaWordpressAPI extends YottaaAPI {

  public $wpy_api_key_optname = 'wpyottaa_api_key';
  public $wpy_user_id_optname = 'wpyottaa_user_id';
  public $wpy_site_id_optname = 'wpyottaa_site_id';
  public $wpy_auto_clear_cache_optname = 'wpyottaa_auto_clear_cache';

  public function __construct() {
    $key = get_option($this->wpy_api_key_optname, '');
    $uid = get_option($this->wpy_user_id_optname, '');
    $sid = get_option($this->wpy_site_id_optname, '');
    parent::__construct($key, $uid, $sid);
  }

  /**
   * Returns all Yottaa parameters.
   *
   * @return array
   */
  public function getParameters() {
    return array("api_key" => get_option($this->wpy_api_key_optname, ""),
           "user_id" => get_option($this->wpy_user_id_optname, ""),
           "site_id" => get_option($this->wpy_site_id_optname, ""),
    );
  }

  /**
   * Updates all Yottaa parameters.
   *
   * @param $key
   * @param $uid
   * @param $sid
   * @return void
   */
  public function updateParameters($key, $uid, $sid) {
    update_option($this->wpy_user_id_optname, $uid);
    update_option($this->wpy_api_key_optname, $key);
    update_option($this->wpy_site_id_optname, $sid);
  }

  /**
   * Deletes all Yottaa parameters.
   *
   * @return void
   */
  public function deleteParameters() {
    delete_option($this->wpy_user_id_optname);
    delete_option($this->wpy_api_key_optname);
    delete_option($this->wpy_site_id_optname);
  }

  /**
   * Returns auto clear cache parameter.
   *
   * @return
   */
  public function getAutoClearCacheParameter() {
    return get_option($this->wpy_auto_clear_cache_optname, FALSE);
  }

  /**
   * Sets auto clear cache parameter.
   *
   * @param $enabled
   * @return void
   */
  public function setAutoClearCacheParameter($enabled) {
    update_option($this->wpy_auto_clear_cache_optname, $enabled);
  }

  /**
   * Logs a message.
   *
   * @param $message
   * @return void
   */
  public function log($message) {
    if ( WP_DEBUG === true ) {
      if ( is_array($message) || is_object($message) ) {
        error_log( print_r($message, true) );
      } else {
        error_log( $message );
      }
    }
  }

  /**
   * Post-processes settings return from Yottaa service.
   *
   * @param $json_output
   * @return array
   */
  protected function postProcessingSettings($json_output) {
    $this->log( "wordpress postProcessingSettings" );
        if (!isset($json_output["error"])) {

            $full_pages_key = "(.*)";
            $site_pages_key = ".html";
            $admin_pages_key = "/wp-admin";

            $home_page_caching = 'unknown';
            $site_pages_caching = 'unknown';
            $admin_pages_caching = 'unknown';

            $only_cache_anonymous_users = 'unknown';

            $exclusions = '';
            $excluded_cookie = 'unknown';

            if (isset($json_output["defaultActions"]) && isset($json_output["defaultActions"]["resourceActions"]) && isset($json_output["defaultActions"]["resourceActions"]["htmlCache"])) {
                $html_cachings = $json_output["defaultActions"]["resourceActions"]["htmlCache"];
                foreach ($html_cachings as &$html_caching) {
                    if (isset($html_caching["filters"])) {
                        $filters = $html_caching["filters"];
                        foreach ($filters as &$filter) {
                            if (isset($filter["match"])) {
                                $direction = $filter["direction"] == 1 ? "included" : "excluded";
                                $matches = $filter["match"];
                                foreach ($matches as &$match) {
                                    if (isset($match["condition"])) {
                                        if ($match["condition"] == $site_pages_key && $match["name"] == "URI" && $match["type"] == "0" && $match["operator"] == "CONTAIN") {
                                            $site_pages_caching = $direction;
                                        }
                                        if ($match["condition"] == $full_pages_key && $match["name"] == "URI" && $match["type"] == "0" && $match["operator"] == "REGEX") {
                                            $only_cache_anonymous_users = $direction;
                                        }
                                        if ($match["name"] == "Request-Header" && $match["header_name"] == "Cookie" && $match["condition"] == "wordpress_logged_in" && $match["type"] == "0" && $match["operator"] == "CONTAIN") {
                                            $excluded_cookie = "set";
                                        }
                                        if ($match["condition"] == $admin_pages_key && $match["name"] == "URI" && $match["type"] == "0" && $match["operator"] == "CONTAIN") {
                                            $admin_pages_caching = $direction;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($only_cache_anonymous_users == "unknown" || $excluded_cookie != "set") {
                    $only_cache_anonymous_users = "unknown";
                    $excluded_cookie = "unknown";
                }
            }

            if (isset($json_output["defaultActions"]) && isset($json_output["defaultActions"]["filters"])) {
                $filters = $json_output["defaultActions"]["filters"];
                foreach ($filters as &$filter) {
                    if (isset($filter["match"])) {
                        if ($filter["direction"] == 0) {
                            $matches = $filter["match"];
                            foreach ($matches as &$match) {
                                if (isset($match["condition"])) {
                                    if ($exclusions != '') {
                                        $exclusions = $exclusions . ' ; ';
                                    }
                                    $exclusions = $exclusions . $match["condition"];
                                }
                            }
                        }
                    }
                }
            }

            if (isset($json_output["resourceRules"])) {
                $resourceRules = $json_output["resourceRules"];
                foreach ($resourceRules as &$resourceRule) {
                    if (isset($resourceRule["special_type"]) && $resourceRule["special_type"] == "home") {
                        if ($resourceRule["enabled"]) {
                            $home_page_caching = 'included';
                        }
                    }
                }
            }

            return array('home_page_caching' => $home_page_caching,
                         'site_pages_caching' => $site_pages_caching,
                         'admin_pages_caching' => $admin_pages_caching,
                         'only_cache_anonymous_users' => $only_cache_anonymous_users,
                         'exclusions' => $exclusions);
        } else {
            return $json_output;
        }
  }
}

/**
 * Wrapper function for Yottaa Wordpress API Class.
 */
function yottaa_api_wordpress() {
  static $api;
  if (!isset($api)) {
    $api = new YottaaWordpressAPI();
  }
  return $api;
}