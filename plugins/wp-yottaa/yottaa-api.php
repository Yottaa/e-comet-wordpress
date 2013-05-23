<?php

/**
 * @file
 * Base Yottaa API Class for PHP based plugins.
 */

class YottaaAPI {

  /**
   * The URL of the API.
   */
  private $api = 'https://api.yottaa.com/';

  /**
   * The API key.
   */
  private $key;

  /**
   * The user id.
   */
  private $uid;

  /**
   * The site id.
   */
  private $sid;

  /**
   * Constructor.
   */
  public function __construct($key, $uid, $sid) {
    $this->key = $key;
    $this->uid = $uid;
    $this->sid = $sid;
  }

  /**
   * Checks if it has all required parameters.
   */
  public function isEmpty() {
    return empty($this->key) || empty($this->uid) || empty($this->sid);
  }

  /**
   * Creates an account.
   *
   * @param $name
   * @param $email
   * @param $phone
   * @param $site
   * @return mixed
   */
  public function createAccount($name, $email, $phone, $site) {
    $user_id = '4d34f75b74b1553ba500007f';
    $api_key = '455df7500258012f663b12313d145ceb';

    if (is_array($name)) {
        $fname = isset($name['first_name']) ? $name['first_name'] : "";
        $lname = isset($name['last_name']) ? $name['last_name'] : "";
    }
    else {
        list($fname, $lname) = explode(" ", $name);
    }

    return $this->call('partners/' . $user_id . '/accounts', array(
      'first_name' => $fname,
      'last_name' => $lname,
      'email' => $email,
      'phone' => $phone,
      'site' => $site,
    ), 'POST', $api_key);
  }

  /**
   * Returns Yottaa site full settings and performances post-processing.
   */
  public function getSettings() {
    $result = $this->call('sites/' . $this->sid . '/settings', array(
      'user_id' => $this->uid,
    ), 'GET', $this->key);
    return $this->postProcessingSettings($result);
  }

  /**
   * Returns site status.
   */
  public function getStatus() {
    return $this->call('sites/' . $this->sid, array(
      'user_id' => $this->uid,
    ), 'GET', $this->key);
  }

  /**
   * Returns site status.
   *
   * @param $key
   * @param $uid
   * @param $sid
   * @return mixed
   */
  public function getRuntimeStatus($key, $uid, $sid) {
    return $this->call('sites/' . $sid, array(
      'user_id' => $uid,
    ), 'GET', $key);
  }

  /**
   * Flushes cache.
   */
  public function flush() {
    return $this->call('sites/' . $this->sid . '/flush_cache', array(
      'user_id' => $this->uid,
    ), 'PUT', $this->key);
  }

  /**
   * Flushes cache by path(s).
   *
   * @param $path_configs
   * @return mixed
   */
  public function flushPaths($path_configs) {
    $aggregated_return = array();
    foreach ($path_configs as $path_config) {
      $result = $this->call('sites/' . $this->sid . '/purge_cache?user_id=' . $this->uid . '&type=html', $path_config, 'POST', $this->key, TRUE);
      array_push($aggregated_return, array("config" => $path_config, "result" => $result));
    }
    return $aggregated_return;
  }

  /**
   * Pauses optimization.
   */
  public function pause() {
    return $this->call('optimizers/' . $this->sid . '/pause', array(
      'user_id' => $this->uid,
    ), 'PUT', $this->key);
  }

  /**
   * Resumes optimization.
   */
  public function resume() {
    return $this->call('optimizers/' . $this->sid . '/resume', array(
      'user_id' => $this->uid,
    ), 'PUT', $this->key);
  }

  /**
   * Makes an api call.
   *
   * @param $path
   * @param $params
   * @param $method
   * @param $key
   * @return mixed
   */
  private function call($path, $params, $method, $key , $post_json=FALSE) {
    $output = $this->post_async($this->api . $path, $params, $method, $key, $post_json);
    $json_results = json_decode($this->parseHttpResponse($output), TRUE);
    if (!isset($json_results["error"]) && isset($json_results["error_response"])) {
      $json_results["error"] = $json_results["error_response"];
      unset($json_results["error_response"]);
    }
    return $json_results;
  }

  /**
   * Makes an asynchronous REST call.
   *
   * @param $url
   * @param $params
   * @param $method
   * @param $api_key
   * @return string
   */
  private function post_async($url, $params, $method, $api_key, $post_json = FALSE) {
    if (!$post_json) {
      foreach ($params as $key => &$val) {
        if (is_array($val)) $val = implode(',', $val);
        $post_params[] = $key . '=' . urlencode($val);
      }
      $post_string = implode('&', $post_params);
    }

    $parts = parse_url($url);

    $fp = fsockopen("ssl://" . $parts['host'],
            isset($parts['port']) ? $parts['port'] : 443,
            $errno, $errstr, 30);

    // Data goes in the path for a GET request
    if ($post_json) {
      $parts['path'] .= '?' . $parts['query'];
    }
    else {
      $parts['path'] .= '?' . $post_string;
    }

    $post_data = $post_json ? json_encode($params) : "";

    $out = $method . " " . $parts['path'] . " HTTP/1.1\r\n";
    $out .= "Host: " . $parts['host'] . "\r\n";
    $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $out .= "Content-Length: " . strlen($post_data) . "\r\n";
    $out .= "YOTTAA-API-KEY: " . $api_key . "\r\n";
    $out .= "Connection: Close\r\n\r\n";

    if ($post_json) {
      $out .= $post_data;
    }

    if ($this->getEnableLoggingParameter() == 1) {
        $this->log($out);
    }

    fwrite($fp, $out);
    $result = "";
    while (!feof($fp)) {
      $result .= fgets($fp, 128);
    }
    fclose($fp);

    if ($this->getEnableLoggingParameter() == 1) {
        $this->log($result);
    }

    return $result;
  }

  /**
   * Accepts provided http content, checks for a valid http response,
   * unchunks if needed, returns http content without headers on
   * success, FALSE on any errors.
   *
   * @param NULL $content
   * @return bool|string
   */
  private function parseHttpResponse($content = NULL) {
    if (empty($content)) {
      return '{"error" : "Empty Response"}';
    }
    // split into array, headers and content.
    $hunks = explode("\r\n\r\n", trim($content));
    if (!is_array($hunks)) {
      return json_encode(array("error"=>trim($content)));
    }
    // add support for empty body
    if (count($hunks) < 2) {
      $header = $hunks[count($hunks) - 1];
      $body = "";
    }
    else {
      $header = $hunks[count($hunks) - 2];
      $body = $hunks[count($hunks) - 1];
    }
    $headers = explode("\n", $header);
    unset($hunks);
    unset($header);
    if (!$this->validateHttpResponse($headers)) {
      return json_encode(array("error"=>trim($content)));
    }
    if (in_array('Transfer-Coding: chunked', $headers)) {
      return trim($this->unchunkHttpResponse($body));
    }
    else {
      return trim($body);
    }
  }

  /**
   * Validates http responses by checking header.  Expects array of
   * headers as argument.  Returns boolean.
   *
   * @param NULL $headers
   * @return bool
   */
  private function validateHttpResponse($headers = NULL) {
    if (!is_array($headers) or count($headers) < 1) {
      return FALSE;
    }
    switch (trim(strtolower($headers[0]))) {
      case 'http/1.0 100 ok':
      case 'http/1.0 200 ok':
      case 'http/1.1 100 ok':
      case 'http/1.1 200 ok':
        return TRUE;
        break;
    }
    return FALSE;
  }

  /**
   * Un-chunks http content.  Returns unchunked content on success,
   * FALSE on any errors...  Borrows from code posted above by
   * jbr at ya-right dot com.
   *
   * @param NULL $str
   * @return bool|NULL|string
   */
  private function unchunkHttpResponse($str = NULL) {
    if (!is_string($str) or strlen($str) < 1) {
      return FALSE;
    }
    $eol = "\r\n";
    $add = strlen($eol);
    $tmp = $str;
    $str = '';
    do {
      $tmp = ltrim($tmp);
      $pos = strpos($tmp, $eol);
      if ($pos === FALSE) {
        return FALSE;
      }
      $len = hexdec(substr($tmp, 0, $pos));
      if (!is_numeric($len) or $len < 0) {
        return FALSE;
      }
      $str .= substr($tmp, ($pos + $add), $len);
      $tmp = substr($tmp, ($len + $pos + $add));
      $check = trim($tmp);
    } while (!empty($check));
    unset($tmp);
    return $str;
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
    $this->key = $key;
    $this->uid = $uid;
    $this->sid = $sid;
  }

  /**
   * Deletes all Yottaa parameters.
   *
   * @return void
   */
  public function deleteParameters() {
    $this->key = NULL;
    $this->uid = NULL;
    $this->sid = NULL;
  }

  /**
   * Post-processes Yottaa site settings.
   *
   * @param $json_output
   * @return array
   */
  protected function postProcessingSettings($json_output) {
    return $json_output;
  }

  /**
   * Logs a message.
   *
   * @param $message
   * @return void
   */
  public function log($message) {
  }

  /**
   * Returns auto clear cache parameter.
   *
   * @return
   */
  public function getEnableLoggingParameter() {
    return 0;
  }
}