<?php
/**
 * @file
 * Contains \Drupal\Tests\shortcode\WebTest\ShortcodeTest.
 */

namespace Drupal\shortcode\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\shortcode\Shortcode\ShortcodeService;

/**
 * Tests the Drupal 8 shortcode module functionality
 *
 * @group shortcode
 */
class ShortcodeTest extends WebTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('filter', 'shortcode');

  /**
   * The shortcode service.
   *
   * @var ShortcodeService $shortcodeService
   */
  private $shortcodeService;

  /**
   * Perform any initial set up tasks that run before every test method
   */
  public function setUp() {
    parent::setUp();
    $this->shortcodeService = \Drupal::service('shortcode');
  }

  /**
   * WIP
   */
  public function testTextFormat() {
    //$this->drupalLogin($this->user);
    //$this->drupalGet($path);
    //$this->assertResponse(200);
  }

  /**
   * Tests that the Button shortcode returns the right content.
   */
  public function testButtonShortcode() {

    $sets = array(
      array(
        'input' => '[button]Label[/button]',
        'output' => '<a href="/" class="button" title="Label"><span>Label</span></a>',
        'message' => 'Button shortcode output matches.',
      ),
      array(
        'input' => '[button path="<front>" class="custom-class"]Label[/button]',
        'output' => '<a href="/" class="button custom-class" title="Label"><span>Label</span></a>',
        'message' => 'Button shortcode with custom class output matches.',
      ),
      array(
        'input' => '[button path="http://www.google.com" class="custom-class" title="Title" id="theLabel" style="border-radius:5px;"]Label[/button]',
        'output' => '<a href="/http://www.google.com" class="button custom-class" id="theLabel" style="border-radius:5px;" title="Title"><span>Label</span></a>',
        'message' => 'Button shortcode with custom attributes and absolute output matches.',
      ),
    );

    foreach ($sets as $set) {
      $output = $this->shortcodeService->process($set['input']);
      $this->assertEqual($output, $set['output'], $set['message']);
    }
  }

  /**
   * Tests that the Clear shortcode returns the right content.
   */
  public function testClearShortcode() {

    $sets = array(
      array(
        'input' => '[clear]<div>Other elements</div>[/clear]',
        'output' => '<div class="clearfix"><div>Other elements</div></div>',
        'message' => 'Clear shortcode output matches.',
      ),
      array(
        'input' => '[clear type="s"]<div>Other elements</div>[/clear]',
        'output' => '<span class="clearfix"><div>Other elements</div></span>',
        'message' => 'Clear shortcode with custom type "s" output matches.',
      ),
      array(
        'input' => '[clear type="span"]<div>Other elements</div>[/clear]',
        'output' => '<span class="clearfix"><div>Other elements</div></span>',
        'message' => 'Clear shortcode with custom type "span" output matches.',
      ),
      array(
        'input' => '[clear type="d"]<div>Other elements</div>[/clear]',
        'output' => '<div class="clearfix"><div>Other elements</div></div>',
        'message' => 'Clear shortcode with custom type "d" output matches.',
      ),
      array(
        'input' => '[clear type="d" class="custom-class" id="theLabel" style="background-color: #F00;"]<div>Other elements</div>[/clear]',
        'output' => '<div class="clearfix custom-class" id="theLabel" style="background-color: #F00;"><div>Other elements</div></div>',
        'message' => 'Clear shortcode with custom attributes output matches.',
      ),
    );

    foreach ($sets as $set) {
      $output = $this->shortcodeService->process($set['input']);
      $this->assertEqual($output, $set['output'], $set['message']);
    }
  }

  /**
   * Tests that the Dropcap shortcode returns the right content.
   */
  public function testDropcapShortcode() {

    $sets = array(
      array(
        'input' => '[dropcap]text[/dropcap]',
        'output' => '<span class="dropcap">text</span>',
        'message' => 'Dropcap shortcode output matches.',
      ),
      array(
        'input' => '[dropcap class="custom-class"]text[/dropcap]',
        'output' => '<span class="dropcap custom-class">text</span>',
        'message' => 'Dropcap shortcode with custom class output matches.',
      ),
    );

    foreach ($sets as $set) {
      $output = $this->shortcodeService->process($set['input']);
      $this->assertEqual($output, $set['output'], $set['message']);
    }
  }

  /**
   * Tests that the Image shortcode returns the right content.
   */
  public function testImgShortcode() {

    $sets = array(
      array(
        'input' => '[img src="/abc.jpg" alt="Test image" /]',
        'output' => '<img src="/abc.jpg" class="img" alt="Test image"/>',
        'message' => 'Image shortcode output matches.',
      ),
      array(
        'input' => '[img src="/abc.jpg" class="custom-class" alt="Test image" /]',
        'output' => '<img src="/abc.jpg" class="img custom-class" alt="Test image"/>',
        'message' => 'Image shortcode with custom class output matches.',
      ),
    );

    foreach ($sets as $set) {
      $output = $this->shortcodeService->process($set['input']);
      $this->assertEqual($output, $set['output'], $set['message']);
    }
  }

  /**
   * Tests that the highlight shortcode returns the right content.
   */
  public function testHighlightShortcode() {

    $test_input = '[highlight]highlighted text[/highlight]';
    $expected_output = '<span class="highlight">highlighted text</span>';
    $output = $this->shortcodeService->process($test_input);
    $this->assertEqual($output, $expected_output, 'Highlight shortcode output matches.');

    $test_input = '[highlight class="custom-class"]highlighted text[/highlight]';
    $expected_output = '<span class="highlight custom-class">highlighted text</span>';
    $output = $this->shortcodeService->process($test_input);
    $this->assertEqual($output, $expected_output, 'Highlight shortcode with custom class output matches.');
  }
}