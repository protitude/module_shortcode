<?php
/**
 * @file
 * Contains \Drupal\Tests\shortcode\WebTest\ShortcodeTest.
 */

namespace Drupal\shortcode\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the Drupal 8 demo module functionality
 *
 * @group demo
 */
class ShortcodeTest extends WebTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('filter', 'shortcode');

  /**
   * A simple user with 'access content' permission
   */
  private $user;

  /**
   * Perform any initial set up tasks that run before every test method
   */
  public function setUp() {
    parent::setUp();
    //$this->user = $this->drupalCreateUser(array('access content'));
  }

  /**
   * Tests that the highlight shortcode returns the right content.
   */
  public function testHighlightShortcode() {
    //$this->drupalLogin($this->user);

    //$this->drupalGet($path);
    //$this->assertResponse(200);

    /** @var \Drupal\shortcode\Shortcode\ShortcodeService $shortcodeService */
    $shortcodeService = \Drupal::service('shortcode');

    $test_input = '[highlight]highlighted text[/highlight]';
    $expected_output = '<span class="highlight">highlighted text</span>';
    $output = $shortcodeService->process($test_input);
    $this->assertEqual($output, $expected_output, 'Highlight shortcode output matches.');

    $test_input = '[highlight class="custom-class"]highlighted text[/highlight]';
    $expected_output = '<span class="highlight custom-class">highlighted text</span>';
    $output = $shortcodeService->process($test_input);
    $this->assertEqual($output, $expected_output, 'Highlight shortcode with custom class output matches.');
  }
}