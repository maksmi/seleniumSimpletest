<?php

/**
 * Selenium element.
 */
class SeleniumWebElementHelperBase {

  /**
   * Execute request
   *
   * @param string $http_type
   *   Http type (GET,POST...)
   * @param string $relative_url
   *   URL
   * @param array $variables
   *   Variables (JS, browser, platform)
   *
   * @return array
   *   JSONRespond
   */
  protected function execute($http_type, $relative_url, $variables = NULL) {
    return $this->driver->execute(
    $http_type, "/session/:sessionId/element/" . $this->element_id . $relative_url, $variables
    );
  }

  /**
   * Drag and drop an element.
   * The distance to drag an element should be specified relative to the
   * upper-left corner of the page.
   *
   * @param integer $pixels_right
   *   The number of pixels to drag the element in the horizontal direction.
   *   A positive value indicates the element should be dragged to the right,
   *   while a negative value indicates that it should be dragged to the left.
   * @param integer $pixels_down
   *   The number of pixels to drag the element in the vertical direction.
   *   A positive value indicates the element should be dragged
   *   down towards the bottom of the screen,
   *   while a negative value indicates that it should be dragged
   *   towards the top of the screen.
   */
  public function dragAndDrop($pixels_right, $pixels_down) {
    $variables = array(
      "x" => $pixels_right,
      "y" => $pixels_down,
    );
    $this->execute("POST", "/drag", $variables);
  }

  /**
   * Move the mouse by an offset of the specificed element,
   * the mouse will be moved to the center of the element.
   * If the element is not visible, it will be scrolled into view.
   */
  public function moveCursorCenter() {
    $variables = array("element" => $this->element_id);
    $this->driver->execute("POST", "/session/:sessionId/moveto", $variables);
  }

  /**
   * Move the mouse by an offset of the specificed element.
   * If the element is not visible, it will be scrolled into view.
   *
   * @param integer $right
   *   X offset to move to, relative to the top-left corner of the element.
   * @param integer $down
   *   Y offset to move to, relative to the top-left corner of the element.
   */
  public function moveCursorRelative($right, $down) {
    $variables = array(
      "element" => $this->element_id,
      "xoffset" => $right,
      "yoffset" => $down,
    );
    $this->driver->execute("POST", "/session/:sessionId/moveto", $variables);
  }

  /*
   * Getters for <select> elements
   */

  /**
   * Search for selected option of <select> element on the page.
   * The located element will be returned as a SeleniumWebElement JSON object.
   *
   * @return SeleniumWebElement.object
   *   A SeleniumWebElement JSON object for the located element.
   */
  public function getSelected() {
    // See http://code.google.com/p/selenium/issues/detail?id=1518
    try {
      return $this->getNextElement("css=option[selected]");
      // Does not work in IE8
    } catch (Exception $e) {
      return $this->getNextElement("css=option[selected='selected']");
      // Does not work in IE7
    }
  }

  /**
   * Search for options for <select> element on the page,
   * starting from the identified element.
   * The located elements will be returned as a SeleniumWebElement JSON objects.
   * Elements should be returned in the order located in the DOM.
   *
   * @return array
   *   A list of SeleniumWebElement JSON objects for the located elements.
   */
  public function getOptions() {
    return $this->getAllNextElements("tag name=option");
  }

  /**
   * Setters for <select> elements
   */

  /**
   * Search for <select> element on the page,
   * starting from the identified element,
   * which has option with specificed label.
   *
   * @param string $label
   *   Label of the option for select element
   *
   * @return NULL
   *   NULL
   */
  public function selectLabel($label) {
    $option_element = $this->getNextElement("xpath=//option[text()='" . $label . "']");
    $option_element->select();
  }

  /**
   * Search for <select> element on the page,
   * starting from the identified element,
   * which has option with specificed value.
   *
   * @param string $value
   *   Value of the option for select element
   */
  public function selectValue($value) {
    $option_element = $this->getNextElement("xpath=//option[@value='" . $value . "']");
    $option_element->select();
  }

  /**
   * Search for <select> element on the page,
   * starting from the identified element,
   * which has option with specificed attribute.
   *
   * @param string $index
   *   Index
   */
  public function selectIndex($index) {
    $option_element = $this->getNextElement("xpath=//option[" . $index . "]");
    $option_element->select();
  }

  /**
   * Test if two element IDs refer to the same DOM element.
   *
   * @return boolean
   *   Whether the two IDs refer to the same element.
   */
  public function isSameElementAs($other_element_id) {
    $response = $this->execute("GET", "/equals/" . $other_element_id);
    return $this->driver->GetJSONValue($response);
  }

}

class SeleniumWebElementHelperGeter extends SeleniumWebElementHelperBase {

  /**
   * Get key from $keys.
   *
   * @param string $key_name
   *   Key name
   */
  public function getKey($key_name) {
    if (isset(self::$keys[$key_name])) {
      return json_decode('"' . self::$keys[$key_name] . '"');
    }
    else {
      throw new Exception("Can't type key $key_name");
    }
  }

  /**
   * Search for an element on the page, starting from the identified element.
   * The located element will be returned as a SeleniumWebElement JSON object.
   * Each locator must return the first matching element located in the DOM.
   *
   * @return SeleniumWebElement.object
   *   A SeleniumWebElement JSON object for the located element.
   */
  public function getNextElement($locator) {
    $variables = $this->driver->ParseLocator($locator);
    $response = $this->execute("POST", "/element", $variables);
    $next_element_id = $this->driver->GetJSONValue($response, "ELEMENT");
    return new SeleniumWebElement($this->driver, $next_element_id, $locator);
  }

  /**
   * Search for multiple elements on the page, starting
   * from the identified element.
   * The located elements will be returned as a SeleniumWebElement JSON objects.
   * Elements should be returned in the order located in the DOM.
   *
   * @return array
   *   A list of SeleniumWebElement JSON objects for the located elements.
   */
  public function getAllNextElements($locator) {
    $variables = $this->driver->ParseLocator($locator);
    $response = $this->execute("POST", "/elements", $variables);
    $all_element_ids = $this->driver->GetJSONValue($response, "ELEMENT");
    $all_elements = array();
    foreach ($all_element_ids as $element_ID) {
      $all_elements[] = new SeleniumWebElement($this->driver, $element_ID, $locator);
    }
    return $all_elements;
  }

  /**
   * Query for an element's tag name.
   *
   * @return string
   *   The element's tag name, as a lowercase string.
   */
  public function getTagName() {
    $response = $this->execute("GET", "/name");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Get the value of an element's attribute.
   *
   * @return string|NULL
   *   The value of the attribute, or NULL if it is not set on the element.
   */
  public function getAttributeValue($attribute_name) {
    $response = $this->execute("GET", "/attribute/" . $attribute_name);
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Determine an element's location on the page.
   * The point (0, 0) refers to the upper-left corner of the page.
   * The element's coordinates are returned as an array with x and y properties.
   *
   * @return array(x:integer,y:integer)
   *   The X and Y coordinates for the element on the page.
   */
  public function getLocation() {
    $response = $this->execute("GET", "/location");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Determine an element's size in pixels.
   * The size will be returned as an array with width and height properties.
   *
   * @return array(width:integer,height:integer)
   *   The width and height of the element, in pixels.
   */
  public function getSize() {
    $response = $this->execute("GET", "/size");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Query the value of an element's computed CSS property.
   * The CSS property to query should be specified using the CSS property name,
   * not the JavaScript property name (e.g. background-color instead
   * of backgroundColor).
   *
   * @return string
   *   The value of the specified CSS property.
   */
  public function getCssValue($property_name) {
    $response = $this->execute("GET", "/css/" . $property_name);
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Returns the visible text for the element.
   */
  public function getText() {
    $response = $this->execute("GET", "/text");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Query for the value of an element, as determined by its value attribute.
   *
   * @return string|NULL
   *   The element's value, or NULL if it does not have a value attribute.
   */
  public function getValue() {
    $response = $this->execute("GET", "/value");
    return $this->driver->GetJSONValue($response);
  }

}

class SeleniumWebElementHelperSimple extends SeleniumWebElementHelperGeter {

  /**
   * Click on an element.
   */
  public function click() {
    $this->execute("POST", "/click");
  }

  /**
   * Submit a FORM element. The submit command may also be applied to any
   * element that is a descendant of a FORM element.
   */
  public function submit() {
    $this->execute("POST", "/submit");
  }

  /**
   * Clear a TEXTAREA or text INPUT element's value.
   */
  public function clear() {
    $this->execute("POST", "/clear");
  }

  /**
   * Move the mouse over an element.
   * Not supported as of Selenium 2.0b3
   */
  public function hover() {
    $this->execute("POST", "/hover");
  }

  /**
   * Select an OPTION element, or an INPUT element of type
   * checkbox or radiobutton.
   */
  public function select() {
    $this->execute("POST", "/selected");
  }

}

class SeleniumWebElement extends SeleniumWebElementHelperSimple {

  protected $driver;

  /**
   * ID of the session to route the command to.
   *
   * @var string
   */
  protected $elementID;

  /**
   * Locator must return the first matching element located in the DOM.
   *
   * @var string
   *  ID
   */
  protected $locator;

  /**
   * UTF-8 Keys.
   *
   * @var string
   *  locator
   */
  protected static $keys = array(
    'NullKey' => "\uE000",
    'CancelKey' => "\uE001",
    'HelpKey' => "\uE002",
    'BackspaceKey' => "\uE003",
    'TabKey' => "\uE004",
    'ClearKey' => "\uE005",
    'ReturnKey' => "\uE006",
    'EnterKey' => "\uE007",
    'ShiftKey' => "\uE008",
    'ControlKey' => "\uE009",
    'AltKey' => "\uE00A",
    'PauseKey' => "\uE00B",
    'EscapeKey' => "\uE00C",
    'SpaceKey' => "\uE00D",
    'PageUpKey' => "\uE00E",
    'PageDownKey' => "\uE00F",
    'EndKey' => "\uE010",
    'HomeKey' => "\uE011",
    'LeftArrowKey' => "\uE012",
    'UpArrowKey' => "\uE013",
    'RightArrowKey' => "\uE014",
    'DownArrowKey' => "\uE015",
    'InsertKey' => "\uE016",
    'DeleteKey' => "\uE017",
    'SemicolonKey' => "\uE018",
    'EqualsKey' => "\uE019",
    'Numpad0Key' => "\uE01A",
    'Numpad1Key' => "\uE01B",
    'Numpad2Key' => "\uE01C",
    'Numpad3Key' => "\uE01D",
    'Numpad4Key' => "\uE01E",
    'Numpad5Key' => "\uE01F",
    'Numpad6Key' => "\uE020",
    'Numpad7Key' => "\uE021",
    'Numpad8Key' => "\uE022",
    'Numpad9Key' => "\uE023",
    'MultiplyKey' => "\uE024",
    'AddKey' => "\uE025",
    'SeparatorKey' => "\uE026",
    'SubtractKey' => "\uE027",
    'DecimalKey' => "\uE028",
    'DivideKey' => "\uE029",
    'F1Key' => "\uE031",
    'F2Key' => "\uE032",
    'F3Key' => "\uE033",
    'F4Key' => "\uE034",
    'F5Key' => "\uE035",
    'F6Key' => "\uE036",
    'F7Key' => "\uE037",
    'F8Key' => "\uE038",
    'F9Key' => "\uE039",
    'F10Key' => "\uE03A",
    'F11Key' => "\uE03B",
    'F12Key' => "\uE03C",
    'CommandKey' => "\uE03D",
    'MetaKey' => "\uE03D",
  );

  /**
   * Constructor
   *
   * @param string $driver
   *   Driver
   * @param string $element_ID
   *   Element ID
   * @param string $locator
   *   Locator
   */
  public function __construct($driver, $element_ID, $locator) {
    $this->driver = $driver;
    $this->element_id = $element_ID;
    $this->locator = $locator;
  }

  /**
   * Describe the identified element.
   */
  public function describe() {
    $response = $this->execute("GET", "");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Determine if an element is currently displayed.
   *
   * @return boolean
   *   Whether the element is displayed.
   */
  public function isVisible() {
    $response = $this->execute("GET", "/displayed");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Determine if an element is currently enabled.
   *
   * @return boolean
   *   Whether the element is enabled.
   */
  public function isEnabled() {
    $response = $this->execute("GET", "/enabled");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Determine if an OPTION element, or an INPUT element of type checkbox or
   * radiobutton is currently selected.
   *
   * @return boolean
   *   Whether the element is selected.
   */
  public function isSelected() {
    $response = $this->execute("GET", "/selected");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Toggle whether an OPTION element, or an INPUT element of type checkbox or
   * radiobutton is currently selected.
   *
   * @return boolean
   *   Whether the element is selected after toggling its state.
   */
  public function toggle() {
    $response = $this->execute("POST", "/toggle");
    return $this->driver->GetJSONValue($response);
  }

  /**
   * Query for the value of an element, as determined by its value attribute.
   */
  public function sendKeys($keys) {
    $variables = array(
      "value" => preg_split('//u', $keys, -1, PREG_SPLIT_NO_EMPTY));
    $this->execute("POST", "/value", $variables);
  }

}

