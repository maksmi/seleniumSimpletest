<?php

/**
 * @file
 * Framework file for selenium.
 */
define('SELENIUM_SERVER_URL', 'http://' . variable_get('selenium_server_host', 'localhost:4444') . "/wd/hub");


/**
 * Test case for Selenium test.
 */
class DrupalSeleniumWebTestCaseHelperBase extends SimpleTestCloneTestCase {

  /**
   * Open specific url.
   *
   * @param string $url
   *   URL to open
   */
  function drupalOpenUrl($url) {
    $this->driver->openUrl($url);
  }

  /**
   * Gets the current raw HTML of requested page.
   *
   * @return text
   *   Body text
   */
  protected function drupalGetContent() {
    return $this->driver->getBodyText();
  }

  /**
   * Get the current url of the browser.
   *
   * @return NULL
   *   The current url.
   */
  protected function getUrl() {
    return $this->driver->getUrl();
  }

  /**
   * Get full user info by option
   *
   * @param string $value
   *   value to search
   * @param string $option
   *   option to search
   *
   * @return object
   *   full user info
   */
  public function getUserObjectBy($value, $option = 'name') {
    return user_load(db_query(
    "SELECT uid FROM {users} WHERE $option = $value")->fetchField());
  }

  /**
   * Injects javascript code to disable work of some of the drupal javascripts.
   * For example vertical tabs hides some of the elements on the node form.
   * This leads to situation when Selenium can't access to hidden fields. So if
   * we use drupalPost method that should behave similar to native simpletest
   * method we are not able to submit the form properly.
   *
   * @param array $scripts
   *   scripts
   */
  function disableJs($scripts) {
    $scripts += array(
      'vertical tabs' => TRUE,
    );

    foreach ($scripts as $type => $execute) {
      if (!$execute) {
        continue;
      }
      $javascript = '';
      switch ($type) {
        case 'vertical tabs':
          $javascript = 'jQuery(".vertical-tabs-pane").show();';
          break;
      }
      // Inject javascript.
      if (!empty($javascript)) {
        $this->driver->executeJsSync($javascript);
      }
    }
  }

  /**
   * Get name of current test running.
   *
   * @return string
   *   Test name
   */
  protected function getTestName() {
    $backtrace = debug_backtrace();
    foreach ($backtrace as $bt_item) {
      if (strtolower(substr($bt_item['function'], 0, 4)) == 'test') {
        return $bt_item['function'];
      }
    }
  }

  /**
   * Implements getSelectedItem.
   * Get the selected value from a select field.
   *
   * @param string $element
   *   SimpleXMLElement select element.
   *
   * @return string
   *   The selected options array.
   */
  protected function getSelectedItem(SeleniumWebElement $element) {
    $result = array();
    foreach ($element->getOptions() as $option) {
      if ($option->isSelected()) {
        $result[] = $option;
      }
    }
    return $result;
  }

  /**
   * Take a screenshot from current page.
   * Save it to verbose directory and add verbose message.
   */
  function verboseScreenshot() {
    // Take screenshot of current page.
    $screenshot = FALSE;
    try {
      $screenshot = $this->driver->getScreenshot();
    } catch (Exception $e) {
      $this->verbose(t('No support for screenshots in %driver', array('%driver' => get_class($this->driver))));
    }
    if ($screenshot) {
      // Prepare directory.
      $directory = $this->originalFileDirectory . '/simpletest/verbose/screenshots';
      $writable = file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
      if ($writable) {
        $testname = $this->getTestName();
        // Trying to save screenshot to verbose directory.
        $file = file_unmanaged_save_data($screenshot, $this->originalFileDirectory . '/simpletest/verbose/screenshots/' . $testname . '.png', FILE_EXISTS_RENAME);

        // Adding verbose message with link to screenshot.
        $this->error(l(t('Screenshot created.'), $GLOBALS['base_url'] . '/' . $file, array('attributes' => array('target' => '_blank'))), 'User notice');
      }
    }
  }

}

class DrupalSeleniumWebTestCaseHelperAssertField extends DrupalSeleniumWebTestCaseHelperBase {

  /**
   * Asserts that a field exists with the given name or id.
   *
   * @param string $field
   *   Name or id of field to assert.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertField($field, $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement("name=$field");
    } catch (Exception $e) {
      try {
        $element = $this->driver->getElement("id=$field");
      } catch (Exception $e) {
        $element = FALSE;
      }
    }
    return $this->assertTrue(!empty($element), $message ? $message : t('Field %locator found', array('%locator' => $field)), $group);
  }

  /**
   * Implements assertNoField.
   *
   * @param string $field
   *   Name or id of field to assert.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoField($field, $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement("name=$field");
    } catch (Exception $e) {
      try {
        $element = $this->driver->getElement("id=$field");
      } catch (Exception $e) {
        $element = FALSE;
      }
    }
    return $this->assertTrue(empty($element), $message ? $message : t('Field %locator not found', array('%locator' => $field)), $group);
  }

  /**
   * Implements assertFieldChecked.
   * Asserts that a checkbox field in the current page is checked.
   *
   * @param string $locator
   *   locator
   * @param string $message
   *   Message to display.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldChecked($locator, $message = '') {
    $element = $this->driver->getElement($locator);
    $is_checkbox = $element && ($element->getTagName() == 'checkbox' || $element->getAttributeValue('type') == 'checkbox');
    if ($is_checkbox) {
      $id = $element->getAttributeValue('id');
      $message = $message ? $message : t('Checkbox field @id is checked.', array('@id' => $id));
    }
    else {
      $message = t('There is no element with locator @locator or element is not checkbox.', array('@locator' => $locator));
    }

    return $this->assertTrue($is_checkbox && $element->isSelected(), $message, t('Browser'));
  }

  /**
   * Implements assertNoFieldChecked.
   * Asserts that a checkbox field in the current page is not checked.
   *
   * @param string $locator
   *   locator
   * @param string $message
   *   Message to display.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoFieldChecked($locator, $message = '') {
    $element = $this->driver->getElement($locator);
    $is_checkbox = $element && ($element->getTagName() == 'checkbox' || $element->getAttributeValue('type') == 'checkbox');
    if ($is_checkbox) {
      $id = $element->getAttributeValue('id');
      $message = $message ? $message : t('Checkbox field @id is not checked.', array('@id' => $id));
    }
    else {
      $message = t('There is no element with locator @locator or element is not checkbox.', array('@locator' => $locator));
    }

    return $this->assertTrue($is_checkbox && !$element->isSelected(), $message, t('Browser'));
  }

  /**
   * Asserts that a field exists in the current
   * page with the given name and value.
   *
   * @param name $name
   *   Name of field to assert.
   * @param value $value
   *   Value of the field to assert.
   * @param message $message
   *   Message to display.
   * @param group $group
   *   The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldByName($name, $value = '', $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement("name=$name");
      if ($value) {
        $element = $this->elementValue($element, $value);
      }
    } catch (Exception $e) {
      $element = FALSE;
    }
    return $this->assertTrue(!empty($element), $message ? $message : t('Field found by name'), $group);
  }

  /**
   * Asserts that a field not exists in the current
   * page with the given name and value.
   *
   * @param string $name
   *   Name of field to assert.
   * @param string $value
   *   Value of the field to assert.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoFieldByName($name, $value = '', $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement("name=$name");
      if ($value) {
        $element = $this->elementValue($element, $value);
      }
    } catch (Exception $e) {
      $element = FALSE;
    }
    return $this->assertTrue(empty($element), $message ? $message : t('Field found by name'), $group);
  }

  /**
   * Asserts that a field exists in the current
   * page with the given id and value.
   *
   * @param string $id
   *   Id of field to assert.
   * @param string $value
   *   Value of the field to assert.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldById($id, $value = '', $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement("id=$id");
      if ($value) {
        $element = $this->elementValue($element, $value);
      }
    } catch (Exception $e) {
      $element = FALSE;
    }
    return $this->assertTrue(!empty($element), $message ? $message : t('Field found by id'), $group);
  }

  /**
   * Asserts that a field not exists in the current
   * page with the given id and value.
   *
   * @param string $id
   *   Id of field to assert.
   * @param string $value
   *   Value of the field to assert.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoFieldById($id, $value = '', $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement("id=$id");
      if ($value) {
        $element = $this->elementValue($element, $value);
      }
    } catch (Exception $e) {
      $element = FALSE;
    }
    return $this->assertTrue(empty($element), $message ? $message : t('Field found by id'), $group);
  }

  /**
   * Asserts that a field exists in the current page by the given XPath.
   *
   * @param string $xpath
   *   XPath used to find the field.
   * @param string $value
   *   (optional) Value of the field to assert.
   * @param string $message
   *   (optional) Message to display.
   * @param string $group
   *   (optional) The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoFieldByXPath($xpath, $value = NULL, $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement($xpath);
      if ($value) {
        $element = $this->elementValue($element, $value);
      }
    } catch (Exception $e) {
      $element = FALSE;
    }
    return $this->assertTrue(!empty($element), $message ? $message : t('Field found by Xpath'), $group);
  }

  /**
   * Asserts that a field not exists in the current
   * page with the given XPath.
   *
   * @param string $xpath
   *   XPath used to find the field.
   * @param string $value
   *   (optional) Value of the field to assert.
   * @param string $message
   *   (optional) Message to display.
   * @param string $group
   *   (optional) The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldByXPath($xpath, $value = NULL, $message = '', $group = 'Other') {
    try {
      $element = $this->driver->getElement($xpath);
      if ($value) {
        $element = $this->elementValue($element, $value);
      }
    } catch (Exception $e) {
      $element = FALSE;
    }
    return $this->assertTrue(empty($element), $message ? $message : t('Field found by Xpath'), $group);
  }

}

class DrupalSeleniumWebTestCaseHelperAssert extends DrupalSeleniumWebTestCaseHelperAssertField {

  /**
   * Implements assertTextHelper.
   *
   * @param string $text
   *   text
   * @param string $group
   *   grop
   * @param string $not_exists
   *   text exist
   * @param string $message
   *   message
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertTextHelper($text, $group, $not_exists, $message = '') {
    $this->plainTextContent = filter_xss($this->driver->getBodyText(), array());

    // Remove all symbols of new line as we need raw text here.
    $this->plainTextContent = str_replace("\n", '', $this->plainTextContent);

    if (!$message) {
      $message = !$not_exists ? t('"@text" found', array('@text' => $text)) : t('"@text" not found', array('@text' => $text));
    }
    return $this->assert($not_exists == (strpos($this->plainTextContent, $text) === FALSE), $message, $group);
  }

  /**
   * Implements assertTitle.
   *
   * @param string $title
   *   title content
   * @param string $message
   *   message to display
   * @param string $group
   *   group name
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertTitle($title, $message = '', $group = 'Other') {
    $actual = $this->driver->getPageTitle();
    if (!$message) {
      $message = t(
      'Page title @actual is equal to @expected.', array(
        '@actual' => var_export($actual, TRUE),
        '@expected' => var_export($title, TRUE),
      )
      );
    }
    return $this->assertEqual($actual, $title, $message, $group);
  }

  /**
   * Implements assertLink.
   *
   * @param string $label
   *   Label
   * @param string $index
   *   Index
   * @param string $message
   *   Message
   * @param string $group
   *   Group
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertLink($label, $index = 0, $message = '', $group = 'Other') {
    $links = $this->driver->waitForElements('link=' . $label);
    $message = ($message ? $message : t('Link with label %label found.', array('%label' => $label)));
    return $this->assert(isset($links[$index]), $message, $group);
  }

  /**
   * Pass if a link with the specified label is found, and optional with the
   * specified index.
   *
   * @param string $label
   *   Text between the anchor tags.
   * @param string $index
   *   Link position counting from zero.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to, defaults to 'Other'.
   *
   * @return string
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertNoLink($label, $index = 0, $message = '', $group = 'Other') {
    $links = $this->driver->waitForElements('link=' . $label);
    $message = ($message ? $message : t('Link with label %label not found.', array('%label' => $label)));
    return $this->assert(!isset($links[$index]), $message, $group);
  }

  /**
   * Pass if a link containing a given href (part) is found.
   *
   * @param string $href
   *   The full or partial value of the 'href' attribute of the anchor tag.
   * @param string $index
   *   Link position counting from zero.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to, defaults to 'Other'.
   *
   * @return string
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertLinkByHref($href, $index = 0, $message = '', $group = 'Other') {
    $links = $this->driver->getAllElements("//a[contains(@href, '$href')]");
    $message = ($message ? $message : t('Link containing href %href found.', array('%href' => $href)));
    return $this->assert(isset($links[$index]), $message, $group);
  }

  /**
   * Pass if a link containing a given href (part) is not found.
   *
   * @param string $href
   *   The full or partial value of the 'href' attribute of the anchor tag.
   * @param string $index
   *   Link position counting from zero.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to, defaults to 'Other'.
   *
   * @return string
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertNoLinkByHref($href, $index = 0, $message = '', $group = 'Other') {
    $links = $this->driver->getAllElements("//a[contains(@href, '$href')]");
    $message = ($message ? $message : t('Link containing href %href not found.', array('%href' => $href)));
    return $this->assert(!isset($links[$index]), $message, $group);
  }

  /**
   * Implements assertOptionSelected.
   * Asserts that a select option in the current page is checked.
   *
   * @param string $locator
   *   target
   * @param array $option
   *   Option to assert.
   * @param string $message
   *   Message to display.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   *
   * @todo $id is unusable. Replace with $name.
   */
  protected function assertOptionSelected($locator, $option, $message = '') {
    $selected = FALSE;
    $element = $this->driver->getElement($locator);
    $is_select = $element && $element->getTagName() == 'select';
    if ($is_select) {
      $id = $element->getAttributeValue('id');
      $message = $message ? $message : t('Option @option for field @id is selected.', array('@option' => $option, '@id' => $id));
      $selected_options = $this->getSelectedItem($element);
      foreach ($selected_options as $selected_option) {
        if ($selected_option->getValue() == $option) {
          $selected = TRUE;
          break;
        }
      }
    }
    else {
      $message = t('There is no element with locator @locator or element is not select list.', array('@locator' => $locator));
    }

    return $this->assertTrue($is_select && $selected, $message, t('Browser'));
  }

  /**
   * Implements assertNoOptionSelected.
   * Asserts that a select option in the current page is not checked.
   *
   * @param string $locator
   *   Locator.
   * @param array $option
   *   Option to assert.
   * @param string $message
   *   Message to display.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoOptionSelected($locator, $option, $message = '') {
    $selected = FALSE;
    $element = $this->driver->getElement($locator);
    $is_select = $element && $element->getTagName() == 'select';
    if ($is_select) {
      $id = $element->getAttributeValue('id');
      $message = $message ? $message : t('Option @option for field @id is not selected.', array('@option' => $option, '@id' => $id));
      $selected_options = $this->getSelectedItem($element);
      foreach ($selected_options as $selected_option) {
        if ($selected_option->getValue() == $option) {
          $selected = TRUE;
          break;
        }
      }
    }
    else {
      $message = t('There is no element with locator @locator or element is not select list.', array('@locator' => $locator));
    }

    return $this->assertTrue($is_select && !$selected, $message, t('Browser'));
  }

  /**
   * Asserts that each HTML ID is used for just a single element.
   *
   * @param Message $message
   *   Message to display.
   * @param group $group
   *   The group this message belongs to.
   * @param array $ids_to_skip
   *   An optional array of ids to skip when checking for duplicates. It is
   *   always a bug to have duplicate HTML IDs, so this parameter is to enable
   *   incremental fixing of core code. Whenever a test passes this parameter,
   *   it should add a "todo" comment above the call to this function explaining
   *   the legacy bug that the test wishes to ignore and including a link to an
   *   issue that is working to fix that legacy bug.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoDuplicateIds($message = '', $group = 'Other', $ids_to_skip = array()) {
    try {
      $elements = $this->driver->getAllElements("//*[@id]");
      $status = TRUE;
      foreach ($elements as $element) {
        $id = (string) $element->getAttributeValue("id");
        if (isset($seen_ids[$id]) && !in_array($id, $ids_to_skip)) {
          $this->fail(t('The HTML ID %id is unique.', array('%id' => $id)), $group);
          $status = FALSE;
        }
        $seen_ids[$id] = TRUE;
      }
    } catch (Exception $e) {
      $status = FALSE;
    }
    return $this->assertTrue($status, $message ? $message : t('No Duplicate Ids'), $group);
  }

  /**
   * Pass if the browser's URL matches the given path.
   *
   * @param string $path
   *   The expected system path.
   * @param array $options
   *   (optional) Any additional options to pass for $path to url().
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to, defaults to 'Other'.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertUrl($path, array $options = array(), $message = '', $group = 'Other') {
    if (!$message) {
      $message = t('Current URL is @url.', array(
        '@url' => var_export(url($path, $options), TRUE),
      ));
    }
    $options['absolute'] = TRUE;
    return $this->assertEqual($this->getUrl(), url($path, $options), $message, $group);
  }

  /**
   * Pass if the page title is not the given string.
   *
   * @param string $title
   *   The string the title should not be.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belongs to.
   *
   * @return string
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertNoTitle($title, $message = '', $group = 'Other') {
    if (!$message) {
      $message = t('Page title @actual is not equal to @unexpected.', array(
        '@actual' => var_export($this->driver->getPageTitle(), TRUE),
        '@unexpected' => var_export($title, TRUE),
      ));
    }
    return $this->assertNotEqual($this->driver->getPageTitle(), $title, $message, $group);
  }

}

class DrupalSeleniumWebTestCase extends DrupalSeleniumWebTestCaseHelperAssert {

  protected $driver;

  protected function setUp() {
    $modules = func_get_args();
    parent::setUp($modules);
    $this->driver = new SeleniumFirefoxDriver();
  }
  
  /**
   * Follows a link by name.
   * Will click the first link found with this link text by default, or a
   * later one if an index is given. Match is case insensitive with
   * normalized space. The label is translated label. There is an assert
   * for successful click.
   *
   * @param string $label
   *   Text between the anchor tags.
   * @param string $index
   *   Link position counting from zero.
   *
   * @return string
   *   Page on success, or FALSE on failure.
   */
  protected function clickLink($label, $index = 0) {
    // Assert that link exists.
    if (!$this->assertLink($label, $index)) {
      return;
    }

    // Get link elements.
    $links = $this->driver->waitForElements('link=' . $label);

    $link_element = $links[$index];

    // Get current and target urls.
    $url_before = $this->getUrl();
    $url_target = $link_element->getAttributeValue('href');

    $this->assertTrue(isset($links[$index]), t('Clicked link %label (@url_target) from @url_before', array(
      '%label' => $label,
      '@url_target' => $url_target,
      '@url_before' => $url_before)), t('Browser'));

    // Click on element;
    $link_element->click();
  }

  /**
   * Check the value of the form element.
   *
   * @param string $element
   *   element locator
   * @param string $value
   *   value name
   *
   * @return string
   *   element value
   */
  protected function elementValue($element, $value) {
    switch ($element->getTagName()) {
      case 'input':
        $element_value = $element->getValue();
        break;

      case 'textarea':
        $element_value = $element->getText();
        break;

      case 'select':
        $element_value = $element->getSelected()->getValue();
        $element_text = $element->getSelected()->getText();
        break;
    }
    return $value == $element_value || $value == $element_text;
  }

  /**
   * Execute a POST request on a Drupal page.
   * It will be done as usual POST request with SimpleBrowser.
   *
   * @param string $path
   *   Location of the post form. Either a Drupal path or an absolute path or
   *   NULL to post to the current page. For multi-stage forms you can set the
   *   code
   *   // First step in form.
   *   $edit = array(...);
   *   $this->formSubmit('some_url', $edit, t('Save'));
   *   // Second step in form.
   *   $edit = array(...);
   *   $this->formSubmit(NULL, $edit, t('Save'));
   *   endcode
   *
   * @param array $edit
   *   Field data in an associative array. Changes the current input fields
   *   (where possible) to the values indicated. A checkbox can be set to
   *   TRUE to be checked and FALSE to be unchecked. Note that when a form
   *   contains file upload fields, other fields cannot start with the '@'
   *   character.
   *
   *   Multiple select fields can be set using name[] and setting each of the
   *   possible values. Example:
   *   code
   *   $edit = array();
   *   $edit['name[]'] = array('value1', 'value2');
   *   endcode
   *
   * @param string $submit
   *   Value of the submit button whose click is to be emulated. For example,
   *   t('Save'). The processing of the request depends on this value. For
   *   example, a form may have one button with the value t('Save') and another
   *   button with the value t('Delete'), and execute different code depending.
   *
   * @param array $disable_js
   *   disble Js.
   *
   * @return array
   *   node data
   */
  protected function drupalPost($path, $edit, $submit, $disable_js = array()) {
    $settings = array(
      'body' => $edit['body[und][0][value]'],
      'title' => $edit['title'],
      'changed' => REQUEST_TIME,
    );

    if ($this->getUrl() != $path && !is_null($path)) {
      $this->drupalOpenUrl($path);
    }
    // Disable javascripts that hide elements.
    $this->disableJs($disable_js);
    // Find form elements and set the values.
    foreach ($edit as $selector => $value) {
      $element = $this->driver->getElement("name=$selector");
      // Type of input element. Can be textarea, select or input. If input,
      // we need to check 'type' property.
      $type = $element->getTagName();
      if ($type == 'input') {
        $type = $element->getAttributeValue('type');
      }
      switch ($type) {
        case 'text':
        case 'textarea':
          // Clear element first then send text data.
          $element->clear();
          $element->sendKeys($value);
          break;

        case 'select':
          $element->selectValue($value);
          break;

        case 'radio':
          $elements = $this->driver->getAllElements("name=$selector");
          foreach ($elements as $element) {
            if ($element->getValue() == $value) {
              $element->click();
            }
          }
          break;

        case 'checkbox':
          $elements = $this->driver->getAllElements("name=$selector");
          if (!is_array($value)) {
            $value = array($value);
          }
          foreach ($elements as $element) {
            $element_value = $element->getValue();
            $element_selected = $element->isSelected();
            // Click on element if it should be selected
            // but isn't or if element.
            // shouldn't be selected but it is.
            if ((in_array($element_value, $value) && !$element_selected) ||
            (!in_array($element_value, $value) && $element_selected)) {
              $element->click();
            }
          }
          break;
      }
    }

    // Find button and submit the form.
    $elements = $this->driver->getAllElements("name=op");
    foreach ($elements as $element) {
      $val = $element->getValue();
      if ($val == $submit) {
        $element->submit();

        break;
      }
    }

    // Wait for the page to load.
    $this->driver->waitForElements('css=body');
    $url_expl = explode('/', $this->getUrl());
    $settings['nid'] = $url_expl[count($url_expl) - 1];
    $node = (object) $settings;

    return $node;
  }

}

class SeleniumWebdriverHelperBase {

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

class SeleniumWebdriverHelperGeter extends SeleniumWebdriverHelperCookie {

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

class SeleniumWebdriverHelperEvent extends SeleniumWebdriverHelperGeter {

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
 * Class of the connection to Webdriver.
 *
 * Original implementation https://github.com/chibimagic/WebDriver-PHP
 */
class SeleniumWebdriverHelperCookie extends SeleniumWebdriverHelperBase {

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

class SeleniumWebdriver extends SeleniumWebdriverHelperEvent {

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

/**
 * Selenium element.
 */
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
