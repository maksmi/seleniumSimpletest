<?php

class SeleniumWebDriverHelperBase {

  // See http://code.google.com/p/selenium/
  // wiki/JsonWireProtocol#Response_Status_Codes
  protected static $statusCodes = array(
    0 => array("Success", " The command executed successfully."),
    7 => array("NoSuchElement", " An element could not be located on the page using the given search parameters."),
    8 => array("NoSuchFrame", " A request to switch to a frame could not be satisfied because the frame could not be found."),
    9 => array("UnknownCommand", " The requested resource could not be found, or a request was received using an HTTP method that is not supported by the mapped resource."),
    10 => array("StaleElementReference", " An element command failed because the referenced element is no longer attached to the DOM."),
    11 => array("ElementNotVisible", " An element command could not be completed because the element is not visible on the page."),
    12 => array("InvalidElementState", " An element command could not be completed because the element is in an invalid state (e.g. attempting to click a disabled element)."),
    13 => array("UnknownError", " An unknown server-side error occurred while processing the command."),
    15 => array("ElementIsNotSelectable", " An attempt was made to select an element that cannot be selected."),
    17 => array("JavaScriptError", " An error occurred while executing user supplied JavaScript."),
    19 => array("XPathLookupError", " An error occurred while searching for an element by XPath."),
    23 => array("NoSuchWindow", " A request to switch to a different window could not be satisfied because the window could not be found."),
    24 => array("InvalidCookieDomain", " An illegal attempt was made to set a cookie under a different domain than the current page."),
    25 => array("UnableToSetCookie", " A request to set a cookie's value could not be satisfied."),
    28 => array("Timeout", " A command did not complete before its timeout expired."),
    303 => array("See other", "See other"),
  );

  /**
   * Set the amount of time, in milliseconds, that asynchronous scripts executed
   *
   * @param int $milliseconds
   *   time in mlseconds
   */
  public function setAsyncTimeout($milliseconds) {
    $variables = array("ms" => $milliseconds);
    $this->execute("POST", "/session/:sessionId/timeouts/async_script", $variables);
  }

  /**
   * set implicitWait
   *
   * @param int $milliseconds
   *   time in mlseconds
   */
  function setImplicitWait($milliseconds = 60000) {
    $variables = array("ms" => $milliseconds);
    $this->execute("POST", "/session/:sessionId/timeouts/implicit_wait", $variables);
  }

  /**
   * Get JSON Value
   *
   * @param array $curl_response
   *   Curl response
   * @param string $attribute
   *   Attribute
   *
   * @return array
   *   JSON Value
   *
   * @throws Exception
   */
  public static function GetJSONValue($curl_response, $attribute = NULL) {
    if (!isset($curl_response['body'])) {
      throw new Exception("Response had no body\n{$curl_response['header']}");
    }
    $array = json_decode(trim($curl_response['body']), TRUE);
    if ($array === NULL) {
      throw new Exception("Body could not be decoded as JSON\n{$curl_response['body']}");
    }
    if (!isset($array["value"])) {
      throw new Exception("JSON had no value\n" . print_r($array, TRUE));
    }
    if ($attribute === NULL) {
      $rv = $array["value"];
    }
    else {
      if (isset($array["value"][$attribute])) {
        $rv = $array["value"][$attribute];
      }
      elseif (is_array($array["value"])) {
        $rv = array();
        foreach ($array["value"] as $a_value) {
          if (isset($a_value[$attribute])) {
            $rv[] = $a_value[$attribute];
          }
          else {
            throw new Exception("JSON value did not have attribute $attribute\n" . $array["value"]["message"]);
          }
        }
      }
      else {
        throw new Exception("JSON value did not have attribute $attribute\n" . $array["value"]["message"]);
      }
    }
    return $rv;
  }

  /**
   * Execute call to server.
   *
   * @param string $http_type
   *   http type (POST,GET,DELETE)
   * @param string $relative_url
   *   URL
   * @param array $variables
   *   Variables(JS, browser, platform)
   *
   * @return string
   *   Response
   */
  public function execute($http_type, $relative_url, $variables = NULL) {
    if ($variables !== NULL) {
      $variables = json_encode($variables);
    }
    $relative_url = str_replace(':sessionId', $this->sessionID, $relative_url);
    $full_url = SELENIUM_SERVER_URL . $relative_url;

    $curl = curl_init($full_url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $http_type);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HEADER, TRUE);
    if (($http_type === "POST" || $http_type === "PUT") && $variables !== NULL) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, $variables);
    }
    $full_response = curl_exec($curl);
    curl_close($curl);
    $response_parts = explode("\r\n\r\n", $full_response, 2);
    $response['header'] = $response_parts[0];
    if (!empty($response_parts[1])) {
      $response['body'] = $response_parts[1];
    }

    if (isset($response['body'])) {
      $this->checkResponseStatus($response['body'], $variables);
    }
    return $response;
  }

  /**
   * check response
   *
   * @param string $body
   *   response
   * @param array $variables
   *   variables
   *
   * @throws Exception
   */
  protected function checkResponseStatus($body, $variables) {
    $array = json_decode(trim($body), TRUE);
    if (!is_null($array)) {
      $response_status_code = $array["status"];
      if (!self::$statusCodes[$response_status_code]) {
        throw new Exception("Unknown status code $response_status_code returned from server.\n$body");
      }
      if (!in_array($response_status_code, array(0, 303))) {
        $message = $response_status_code . " - " . self::$statusCodes[$response_status_code][0] . " - " . self::$statusCodes[$response_status_code][1] . "\n";
        $message .= "Arguments: " . print_r($variables, TRUE) . "\n";
        if (isset($array['value']['message'])) {
          $message .= "Message: " . $array['value']['message'] . "\n";
        }
        else {
          $message .= "Response: " . $body . "\n";
        }
        throw new Exception($message);
      }
    }
  }

  /**
   * Helpers.
   *
   * @param string $locator
   *   Locator
   *
   * @return array
   *   (locator type, value)
   */
  public static function ParseLocator($locator) {
    $se1_to_se2 = array(
      "identifier" => "id",
      "id" => "id",
      "name" => "name",
      "xpath" => "xpath",
      "link" => "link text",
      "css" => "css selector",
      // The dom selector in Se1 isn't in Se2.
      // Se2 has 4 new selectors.
      "partial link text" => "partial link text",
      "tag name" => "tag name",
      "class" => "class",
      "class name" => "class name",
    );

    $locator_parts = explode("=", $locator, 2);
    if (array_key_exists($locator_parts[0], $se1_to_se2) && $locator_parts[1]) {
      // Explicit Se1 selector.
      $strategy = $se1_to_se2[$locator_parts[0]];
      $value = $locator_parts[1];
    }
    elseif (in_array($locator_parts[0], $se1_to_se2) && $locator_parts[1]) {
      // Explicit Se2 selector.
      $strategy = $locator_parts[0];
      $value = $locator_parts[1];
    }
    elseif (substr($locator, 0, 2) === "//") {
      // Guess the selector based on Se1.
      $strategy = "xpath";
      $value = $locator;
    }
    elseif (substr($locator, 0, 9) === "document." || substr($locator, 0, 4) === "dom=") {
      throw new Exception("DOM selectors aren't supported in WebDriver: $locator");
    }
    else {
      // Fall back to id.
      $strategy = "id";
      $value = $locator;
    }
    return array("using" => $strategy, "value" => $value);
  }

  /**
   * Inject a snippet of JavaScript into the page for execution in the context
   * of the currently selected frame. The executed script is assumed to be
   * synchronous and the result of evaluating the script is returned to the
   * client.
   *
   * The script argument defines the script to execute in the form of a function
   * body. The value returned by that function will be returned to the client.
   * The function will be invoked with the provided args array and the values
   * may be accessed via the arguments object in the order specified.
   *
   * @param string $javascript
   *   Java script
   * @param string $arguments
   *   Arguments
   *
   * @return string
   *   Execute response
   */
  function executeJsSync($javascript, $arguments = array()) {
    $variables = array(
      "script" => $javascript,
      "args" => $arguments,
    );
    return $this->execute("POST", "/session/:sessionId/execute", $variables);
  }

  /**
   * Inject a snippet of JavaScript into the page for execution in the context
   * of the currently selected frame. The executed script is assumed to be
   * asynchronous and must signal that is done by
   * invoking the provided callback,
   * which is always provided as the final argument to the function. The value
   * to this callback will be returned to the client.
   * Asynchronous script commands may not span page loads. If an unload event
   * is fired while waiting for a script result, an error should be returned
   * to the client.
   * The script argument defines the script to execute in teh form of a function
   * body. The function will be invoked with the provided args array and the
   * values may be accessed via the arguments object in the order specified.
   * The final argument will always be a callback function that must be invoked
   * to signal that the script has finished.
   *
   * @param string $javascript
   *   Java script
   * @param string $arguments
   *   Arguments
   *
   * @return response
   *   Execute response
   */
  public function executeJsAsync($javascript, $arguments = array()) {
    $variables = array(
      "script" => $javascript,
      "args" => $arguments,
    );
    return $this->execute("POST", "/session/:sessionId/execute_async", $variables);
  }

  /**
   * Change focus to another opened window.
   *
   * @param string $window_title
   *   Window title
   */
  public function selectWindow($window_title) {
    $all_window_handles = $this->getAllWindowHandles();
    $all_titles = array();
    $current_title = "";
    foreach ($all_window_handles as $window_handle) {
      $variables = array("name" => $window_handle);
      $this->execute("POST", "/session/:sessionId/window", $variables);
      $current_title = $this->getTitle();
      $all_titles[] = $current_title;
      if ($current_title == $window_title) {
        break;
      }
    }
    if ($current_title != $window_title) {
      throw new Exception("Could not find window with title <$window_title>. Found " . count($all_titles) . " windows: " . implode("; ", $all_titles));
    }
  }

  /**
   * Close the current window.
   */
  public function closeWindow() {
    $this->execute("DELETE", "/session/:sessionId/window");
  }

}
/**
 * Class of the connection to Webdriver.
 *
 * Original implementation https://github.com/chibimagic/WebDriver-PHP
 */
class SeleniumWebDriverHelperCookie extends SeleniumWebDriverHelperBase {

  /**
   * Get all cookies.
   *
   * @return string
   *   JSONValue
   */
  public function getAllCookies() {
    $response = $this->execute("GET", "/session/:sessionId/cookie");
    return $this->GetJSONValue($response);
  }

  /**
   * Get specific cookie.
   *
   * @param string $name
   *   Cookie name.
   * @param string $property
   *   What property to return.
   *
   * @return string
   *   cookie property
   */
  public function getCookie($name, $property = NULL) {
    $all_cookies = $this->getCookies();
    foreach ($all_cookies as $cookie) {
      if ($cookie['name'] == $name) {
        if (is_null($property)) {
          return $cookie;
        }
        return $cookie[$property];
      }
    }
  }

  /**
   * Set cookie.
   *
   * @param string $name
   *   Name of cookie
   * @param string $value
   *   Value
   * @param string $path
   *   Path
   * @param string $domain
   *   Domain
   * @param string $secure
   *   Secure
   * @param string $expiry
   *   Expiry
   */
  function setCookie($name, $value, $path = NULL, $domain = NULL, $secure = FALSE, $expiry = NULL) {
    $variables = array(
      'cookie' => array(
        'name' => $name,
        'value' => $value,
        'secure' => $secure,
      // The documentation says this is optional, but selenium server 2.0b1
      // throws a NullPointerException if it's not provided.
      ),
    );
    if (!is_null($path)) {
      $variables['cookie']['path'] = $path;
    }
    if (!is_null($domain)) {
      $variables['cookie']['domain'] = $domain;
    }
    if (!is_null($expiry)) {
      $variables['cookie']['expiry'] = $expiry;
    }
    $this->execute("POST", "/session/:sessionId/cookie", $variables);
  }

  /**
   * Delete all cookies.
   */
  function deleteAllCookies() {
    $this->execute("DELETE", "/session/:sessionId/cookie");
  }

  /**
   * Delete cookie.
   *
   * @param string $name
   *   Cookie name
   */
  function deleteCookie($name) {
    $this->execute("DELETE", "/session/:sessionId/cookie/" . $name);
  }

}
class SeleniumWebDriverHelperGeter extends SeleniumWebDriverHelperCookie {

  /**
   * Get current URL of the browser.
   *
   * @return string
   *   JSONValue
   */
  public function getUrl() {
    $response = $this->execute("GET", "/session/:sessionId/url");
    return $this->GetJSONValue($response);
  }

  /**
   * Get current page title.
   *
   * @return string
   *   The current page title.
   */
  public function getPageTitle() {
    $response = $this->execute("GET", "/session/:sessionId/title");
    return $this->GetJSONValue($response);
  }

  /**
   * Get current page source.
   *
   * @return string
   *   The current page source
   */
  public function getSource() {
    $response = $this->execute("GET", "/session/:sessionId/source");
    return $this->GetJSONValue($response);
  }

  /**
   * Get visible text of the body.
   *
   * @return string
   *   body text
   */
  public function getBodyText() {
    $result = $this->getElement("tag name=body")->getText();
    return $result;
  }

  /**
   * Get a screenshot of the current page.
   *
   * @return string
   *   png picture
   */
  public function getScreenshot() {
    $response = $this->execute("GET", "/session/:sessionId/screenshot");
    $base64_encoded_png = $this->GetJSONValue($response);
    return base64_decode($base64_encoded_png);
  }

  /**
   * Get element.
   *
   * @param string $locator
   *   target locator
   *
   * @return SeleniumWebElement
   *   new SeleniumWebElement
   */
  public function getElement($locator) {
    $variables = $this->ParseLocator($locator);
    try {
      $response = $this->execute("POST", "/session/:sessionId/element", $variables);
    } catch (Exception $e) {
      return NULL;
    }
    $element_ID = $this->GetJSONValue($response, "ELEMENT");
    return new SeleniumWebElement($this, $element_ID, $locator);
  }

  /**
   * Get all elements.
   *
   * @param string $locator
   *   locator
   *
   * @return array
   *   of SeleniumWebElement objects
   */
  public function getAllElements($locator) {
    $variables = $this->ParseLocator($locator);
    $response = $this->execute("POST", "/session/:sessionId/elements", $variables);
    $element_ids = $this->GetJSONValue($response, "ELEMENT");
    $elements = array();
    foreach ($element_ids as $element_ID) {
      $elements[] = new SeleniumWebElement($this, $element_ID, $locator);
    }
    return $elements;
  }

  /**
   * Get element that currently has focus.
   *
   * @return SeleniumWebElement
   *   new SeleniumWebElement
   */
  public function getActiveElement() {
    $response = $this->execute("POST", "/session/:sessionId/element/active");
    $element_ID = $this->GetJSONValue($response, "ELEMENT");
    return new SeleniumWebElement($this, $element_ID, "active=TRUE");
  }

  /**
   * Retrive current window handle.
   *
   * @return string
   *   JSONValue
   */
  public function getWindowHandle() {
    $response = $this->execute("GET", "/session/:sessionId/window_handle");
    return $this->GetJSONValue($response);
  }

  /**
   * Retrieve list of all window handles available to the session.
   *
   * @return string
   *   JSONValue
   */
  public function getAllWindowHandles() {
    $response = $this->execute("GET", "/session/:sessionId/window_handles");
    return $this->GetJSONValue($response);
  }

}

class SeleniumWebDriverHelperEvent extends SeleniumWebDriverHelperGeter {

  /**
   * Send standard events to active element.
   */
  public function eventCtrlDown() {
    $this->sendModifier("U+E009", TRUE);
  }

  /**
   * Specific key actions
   */
  public function eventCtrlUp() {
    $this->sendModifier("U+E009", FALSE);
  }

  /**
   * Specific key actions
   */
  public function eventShiftDown() {
    $this->sendModifier("U+E008", TRUE);
  }

  /**
   * Specific key actions
   */
  public function eventShiftUp() {
    $this->sendModifier("U+E008", FALSE);
  }

  /**
   * Specific key actions
   */
  public function eventAltDown() {
    $this->sendModifier("U+E00A", TRUE);
  }

  /**
   * Specific key actions
   */
  public function eventAltUp() {
    $this->sendModifier("U+E00A", FALSE);
  }

  /**
   * Specific key actions
   */
  public function eventCommandDown() {
    $this->sendModifier("U+E03D", TRUE);
  }

  /**
   * Specific key actions
   */
  public function eventCommandUp() {
    $this->sendModifier("U+E03D", FALSE);
  }

  /**
   * Move cursor from element.
   *
   * @param string $right
   *   x offset
   * @param string $down
   *   y offset
   */
  public function moveCursor($right, $down) {
    $variables = array(
      "xoffset" => $right,
      "yoffset" => $down,
    );
    $this->execute("POST", "/session/:sessionId/moveto", $variables);
  }

  /**
   * Click mouse button.
   *
   * @param int $button
   *   Button number
   */
  protected function mouseClickButton($button) {
    $variables = array("button" => $button);
    $this->execute("POST", "/session/:sessionId/click", $variables);
  }

}

/**
 * Test case for Selenium test.
 */
class SeleniumWebDriver extends SeleniumWebDriverHelperEvent {

  protected $sessionID;

  /**
   * Destroy session.
   */
  public function __destruct() {
    $this->execute("DELETE", "/session/:sessionId");
  }

  /**
   * Getters
   */

  /**
   * Wait for element.
   *
   * @param string $locator
   *   target locator
   *
   * @return object
   *   element
   */
  public function waitForElements($locator) {
    $timeout = 10;
    $elements = NULL;
    while ($timeout > 0 && empty($elements)) {
      $elements = $this->getAllElements($locator);
      sleep(1);
      $timeout--;
    }

    return $elements;
  }

  /**
   * Wait for visible elements.
   * Check only $item element for visibility.
   *
   * @param string $locator
   *   target locator
   * @param int $item
   *   num item
   *
   * @return object
   *   elements
   */
  public function waitForVisibleElements($locator, $item = 0) {
    $timeout = 10;
    $elements = NULL;
    while ($timeout > 0) {
      $elements = $this->getAllElements($locator);
      if (!empty($elements) && isset($elements[$item])) {
        $element = $elements[$item];
        if ($element->isVisible()) {
          return $elements;
        }
      }
      sleep(1);
      $timeout--;
    }

    return $elements;
  }

  /**
   * Check if element presents on the page.
   *
   * @param string $locator
   *   target locator
   *
   * @return boolean
   *   element present/not present
   */
  public function isElementPresent($locator) {
    try {
      $this->getElement($locator);
      $is_element_present = TRUE;
    } catch (Exception $e) {
      $is_element_present = FALSE;
    }
    return $is_element_present;
  }

  /**
   * Setters.
   */

  /**
   * Navigate to URL.
   *
   * @param string $url
   *   URL to open
   */
  public function openUrl($url) {
    if (is_array($url)) {
      $path = $url[0];
      $options = $url[1];
      $options['absolute'] = TRUE;
      $full_url = url($path, $options);
    }
    else {
      $full_url = url($url, array('absolute' => TRUE));
    }

    $variables = array("url" => $full_url);
    $this->execute("POST", "/session/:sessionId/url", $variables);
  }

  /**
   * Navigate forward in browser's history.
   */
  public function historyForward() {
    $this->execute("POST", "/session/:sessionId/forward");
  }

  /**
   * Navigate back in browser's history.
   */
  public function historyBack() {
    $this->execute("POST", "/session/:sessionId/back");
  }

  /**
   * Refresh the page.
   */
  public function refresh() {
    $this->execute("POST", "/session/:sessionId/refresh");
  }

  /**
   * Change focus to another frame on the page.
   *
   * @param string $identifier
   *   Frame ID
   */
  public function selectFrame($identifier) {
    $variables = array("id" => $identifier);
    $this->execute("POST", "/session/:sessionId/frame", $variables);
  }

  /**
   * Send an event to the active element to depress or release a modifier key.
   *
   * @param string $modifier_code
   *   modifier code
   * @param string $is_down
   *   is down
   */
  protected function sendModifier($modifier_code, $is_down) {
    $variables = array(
      'value' => $modifier_code,
      'isdown' => $is_down,
    );
    $this->execute("POST", "/session/:sessionId/modifier", $variables);
  }

}

/**
 * Class of the connection to Firefox.
 */
class SeleniumFirefoxDriver extends SeleniumWebDriver {

  /**
   * Call for firefox driver
   * @throws Exception
   */
  function __construct() {
    $database_prefix = $GLOBALS['drupal_test_info']['test_run_id'];
    if (preg_match('/simpletest\d+/', $database_prefix, $matches)) {
      $user_agent = drupal_generate_test_ua($matches[0]);
    }
    else {
      throw new Exception('Test is not ready to init connection to Webdriver (no database prefix)');
    }

    $temporary_path = file_directory_temp();
    file_prepare_directory($temporary_path);
    $zip_file_path = $temporary_path . '/' . $database_prefix . '_firefox_profile.zip';

    // Generate Firefox profile.
    $zip = new ZipArchive();
    $res = $zip->open($zip_file_path, ZipArchive::CREATE);
    if ($res === TRUE) {
      $zip->addFromString(
      'prefs.js', 'user_pref("general.useragent.override", "' . $user_agent . '");'
      );
      $zip->close();
    }
    else {
      throw new Exception('Cant create firefox profile ' . $zip_file_path);
    }

    // By specifications of the Webdriver we should encode firefox
    // profile zip archive with base64.
    $firefox_profile = base64_encode(file_get_contents($zip_file_path));

    // Start browser.
    $capabilities = array(
      'browserName' => 'firefox',
      'javascriptEnabled' => TRUE,
      'platform' => 'ANY',
      'firefox_profile' => $firefox_profile,
    );

    $variables = array("desiredCapabilities" => $capabilities);
    $response = $this->execute("POST", "/session", $variables);

    // Parse out session id.
    preg_match("/\nLocation:.*\/(.*)\n/", $response['header'], $matches);

    if (count($matches) > 0) {
      $this->sessionID = trim($matches[1]);
    }
    else {
      $message = "Did not get a session id from " . SELENIUM_SERVER_URL . "\n";
      if (!empty($response['body'])) {
        $message .= $response['body'];
      }
      elseif (!empty($response['header'])) {
        $message .= $response['header'];
      }
      else {
        $message .= "No response from server.";
      }
      throw new Exception($message);
    }
  }
}
