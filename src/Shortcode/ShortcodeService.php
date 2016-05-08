<?php
/**
 * @file
 * Contains \Drupal\shortcode\Shortcode\ShortcodeService
 */

namespace Drupal\shortcode\Shortcode;

use Drupal\filter\Plugin\FilterInterface;
use Drupal\Core\Language\Language;
use Drupal\Component\Plugin\PluginManagerInterface;

class ShortcodeService {

  /**
   * Returns array of shortcode plugin definitions enabled for the filter.
   *
   * @param FilterInterface $filter
   *   The filter. Defaults to NULL, where all shortcode plugins will be
   *   returned.
   *
   * @param bool $reset
   *   TRUE if the static cache should be reset. Defaults to FALSE.
   *
   * @return array
   *   Array of shortcode plugin definitions.
   */
  function getShortcodePlugins(FilterInterface $filter = NULL, $reset = FALSE) {
    $shortcodes = &drupal_static(__FUNCTION__);

    if (!isset($shortcodes) || $reset) {
      /** @var PluginManagerInterface $type */
      $type = \Drupal::service('plugin.manager.shortcode');

      $definitions_raw = $type->getDefinitions();
      $definitions = array();
      foreach ($definitions_raw as $definition) {
        $definitions[$definition['id']] = $definition;
      }

      // Alteration of the ShortCode plugin definitions should utilize
      // plugin manager's $alterHook, instead of D7's drupal_alter.
      //drupal_alter('shortcode_info', $definitions);

      $shortcodes = array(
        'plugins' => $definitions,
        'filters' => array(),
      );
    }

    // If filter is given, only return plugin definitions enabled on the filter.
    if ($filter) {
      $filter_id = $filter->getPluginId();
      if (!isset($shortcodes['filters'][$filter_id])) {
        $settings = $filter->settings;


        $enabled_shortcodes = array();
        foreach ($settings as $shortcode_id => $status) {
          if ($status && isset($shortcodes['plugins'][$shortcode_id])) {
            $enabled_shortcodes[$shortcode_id] = $shortcodes['plugins'][$shortcode_id];
          }
        }
        $shortcodes['filters'][$filter_id] = $enabled_shortcodes;
      }

      return $shortcodes['filters'][$filter_id];
    }

    // Return all defined shortcode plugin definitions.
    return $shortcodes['plugins'];

  }

  /**
   * Creates shortcode plugin instance or loads from static cache.
   *
   * @param string $shortcode_id
   *   The shortcode plugin id.
   *
   * @return \Drupal\shortcode\Plugin\ShortcodeInterface
   *   The plugin instance.
   */
  function getShortcodePlugin($shortcode_id) {
    $plugins = &drupal_static(__FUNCTION__, array());
    if (!isset($plugins[$shortcode_id])) {

      /** @var \Drupal\shortcode\Shortcode\ShortcodePluginManager $type */
      $type = \Drupal::service('plugin.manager.shortcode');

      $plugins[$shortcode_id] = $type->createInstance($shortcode_id);
    }
    return $plugins[$shortcode_id];
  }

  /**
   * Checking the given tag is valid Shortcode tag or not.
   *
   * @param string $tag
   *   The tag name.
   *
   * @return bool
   *   Returns TRUE if the given $tag is valid shortcode tag.
   */
  public function isValidShortcodeTag($tag) {
    $shortcodes = $this->getShortcodePlugins();
    // TODO: This is case-sensitive right now, consider if it should be.
    return isset($shortcodes[$tag]);
  }

  /**
   * Helper function to decide the given param is a bool value.
   *
   * @param mixed $var
   *   The variable.
   *
   * @return bool
   *   TRUE if $var is a booleany value.
   */
  protected function isBool($var) {
    switch (strtolower($var)) {
      case FALSE:
      case 'false':
      case 'no':
      case '0':
        $res = FALSE;
        break;

      default:
        $res = TRUE;
        break;
    }

    return $res;
  }

  /**
   * Processes the Shortcodes according to the text and the text format.
   *
   * @param string $text
   *   The string containing shortcodes to be processed.
   * @param string $langcode
   *   The language code of the text to be filtered.
   * @var FilterInterface $filter
   *   The text filter.
   *
   * @return string
   *   The processed string.
   */
  public function process($text, $langcode = Language::LANGCODE_NOT_SPECIFIED, FilterInterface $filter = NULL) {
    $shortcodes = $this->getShortcodePlugins($filter);

    // Processing recursively, now embedding tags within other tags is supported!
    $chunks = preg_split('!(\[{1,2}.*?\]{1,2})!', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    $heap = array();
    $heap_index = array();

    foreach ($chunks as $c) {

      if (!$c) {
        continue;
      }

      $escaped = FALSE;

      if ((substr($c, 0, 2) == '[[') && (substr($c, -2, 2) == ']]')) {
        $escaped = TRUE;
        // Checks media tags, eg: [[{ }]].
        if ((substr($c, 0, 3) != '{') && (substr($c, -3, 1) != '}')) {
          // Removes the outer [].
          $c = substr($c, 1, -1);
        }
      }
      // Decide this is a Shortcode tag or not.
      if (!$escaped && ($c[0] == '[') && (substr($c, -1, 1) == ']')) {
        // The $c maybe contains Shortcode macro.

        // This is maybe a self-closing tag.
        // Removes outer [].
        $original_text = $c;
        $c = substr($c, 1, -1);
        $c = trim($c);

        $ts = explode(' ', $c);
        $tag = array_shift($ts);
        $tag = trim($tag, '/');

        if (!$this->isValidShortcodeTag($tag)) {
          // This is not a valid shortcode tag, or the tag is not enabled.
          array_unshift($heap_index, '_string_');
          array_unshift($heap, $original_text);
        }
        // This is a valid shortcode tag, and self-closing.
        elseif (substr($c, -1, 1) == '/') {
          // Processes a self closing tag, - it has "/" at the end-
          /*
           * The exploded array elements meaning:
           * 0 - the full tag text?
           * 1/5 - An extra [] to allow for escaping Shortcodes with double [[]].
           * 2 - The Shortcode name.
           * 3 - The Shortcode argument list.
           * 4 - The content of a Shortcode when it wraps some content.
           */

          $m = array(
            $c,
            '',
            $tag,
            implode(' ', $ts),
            NULL,
            '',
          );
          array_unshift($heap_index, '_string_');
          array_unshift($heap, $this->processTag($m, $shortcodes));
        }
        // A closing tag, we can process the heap.
        elseif ($c[0] == '/') {
          $closing_tag = substr($c, 1);

          $process_heap = array();
          $process_heap_index = array();
          $found = FALSE;

          // Get elements from heap and process.
          do {
            $tag = array_shift($heap_index);
            $heap_text = array_shift($heap);

            if ($closing_tag == $tag) {
              // Process the whole tag.
              $m = array(
                $tag . ' ' . $heap_text,
                '',
                $tag,
                $heap_text,
                implode('', $process_heap),
                '',
              );
              $str = $this->processTag($m, $shortcodes);
              array_unshift($heap_index, '_string_');
              array_unshift($heap, $str);
              $found = TRUE;
            }
            else {
              array_unshift($process_heap, $heap_text);
              array_unshift($process_heap_index, $tag);
            }
          } while (!$found && $heap);

          if (!$found) {
            foreach ($process_heap as $val) {
              array_unshift($heap, $val);
            }
            foreach ($process_heap_index as $val) {
              array_unshift($heap_index, $val);
            }
          }

        }
        // A starting tag. Add into the heap.
        else {
          array_unshift($heap_index, $tag);
          array_unshift($heap, implode(' ', $ts));
        }
      }
      else {
        // Maybe not found a pair?
        array_unshift($heap_index, '_string_');
        array_unshift($heap, $c);
      }
      // End of foreach.
    }

    return (implode('', array_reverse($heap)));
  }

  /**
   * Provides Html corrector for wysiwyg editors.
   *
   * Correcting p elements around the divs. <div> elements are not allowed
   * in <p> so remove them.
   *
   * @param string $text
   *   Text to be processed.
   * @param string $langcode
   *   The language code of the text to be filtered.
   * @param FilterInterface $filter
   *   The filter plugin that triggered this process.
   *
   * @return string
   *   The processed string.
   */
  public function postprocessText($text, $langcode, FilterInterface $filter = NULL) {

    //preg_match_all('/<p>s.*<!--.*-->.*<div/isU', $text, $r);
    //dpm($r, '$r');

    // Take note these are disrupted by the comments inserted by twig debug mode.
    $patterns = array(
      '|#!#|is',
      '!<p>(&nbsp;|\s)*(<\/*div>)!is',
      '!<p>(&nbsp;|\s)*(<div)!is',
      //'!<p>(&nbsp;|\s)*(<!--(.*?)-->)*(<div)!is', // Trying to ignore HTML comments
      '!(<\/div.*?>)\s*</p>!is',
      '!(<div.*?>)\s*</p>!is',
    );

    $replacements = array(
      '',
      '\\2',
      '\\2',
      //'\\3',
      '\\1',
      '\\1');
    return preg_replace($patterns, $replacements, $text);
  }

  /**
   * Regular Expression callable for do_shortcode() for calling Shortcode hook.
   *
   * See for details of the match array contents.
   *
   * @param array $m
   *   Regular expression match array.
   *
   *     0 - the full tag text?
   *     1/5 - An extra [ or ] to allow for escaping shortcodes with double [[]]
   *     2 - The Shortcode name
   *     3 - The Shortcode argument list
   *     4 - The content of a Shortcode when it wraps some content.
   *
   * @param array $enabled_shortcodes
   *   Array of enabled shortcodes for the active text format.
   *
   * @return string|FALSE
   *   FALSE on failure.
   */
  protected function processTag($m, $enabled_shortcodes) {
    $shortcode_id = $m[2];
    $shortcode = NULL;

    if (isset($enabled_shortcodes[$shortcode_id])){
      $shortcode = $this->getShortcodePlugin($shortcode_id);
    }

    // Process if shortcode exists and enabled.
    if ($shortcode) {
      $attr = $this->parseAttrs($m[3]);

      // This is an enclosing tag, means extra parameter is present.
      if (!is_null($m[4])) {
        return $m[1] . $shortcode->process($attr, $m[4]) . $m[5];
      }
      // This is a self-closing tag.
      else {
        return $m[1] . $shortcode->process($attr, NULL) . $m[5];
      }
    }
    // Shortcode does not exist or is not enabled.
    else {

      // This is an enclosing tag, means extra parameter is present.
      if (!is_null($m[4])) {
        return $m[1] . $m[4] . $m[5];
      }
      // This is a self-closing tag.
      else{
        return $m[1] . $m[5];
      }
    }
  }

  /**
   * Retrieve all attributes from the Shortcodes tag.
   *
   * The attributes list has the attribute name as the key and the value of the
   * attribute as the value in the key/value pair. This allows for easier
   * retrieval of the attributes, since all attributes have to be known.
   *
   * @param string $text
   *   The Shortcode tag attribute line.
   *
   * @return array
   *   List of attributes and their value.
   */
  protected function parseAttrs($text) {
    $attributes = array();
    $pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
    $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);
    $text = html_entity_decode($text);
    if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER)) {
      foreach ($match as $m) {
        if (!empty($m[1])) {
          $attributes[strtolower($m[1])] = stripcslashes($m[2]);
        }
        elseif (!empty($m[3])) {
          $attributes[strtolower($m[3])] = stripcslashes($m[4]);
        }
        elseif (!empty($m[5])) {
          $attributes[strtolower($m[5])] = stripcslashes($m[6]);
        }
        elseif (isset($m[7]) and strlen($m[7])) {
          $attributes[] = stripcslashes($m[7]);
        }
        elseif (isset($m[8])) {
          $attributes[] = stripcslashes($m[8]);
        }
      }
    }
    else {
      $attributes = ltrim($text);
    }
    return $attributes;
  }

  /**
   * Retrieve the Shortcode regular expression for searching.
   *
   * The regular expression combines the Shortcode tags in the regular expression
   * in a regex class.
   *
   * The regular expression contains 6 different sub matches to help with parsing.
   *
   * 1/6 - An extra [ or ] to allow for escaping shortcodes with double [[]]
   * 2 - The Shortcode name
   * 3 - The Shortcode argument list
   * 4 - The self closing /
   * 5 - The content of a Shortcode when it wraps some content.
   *
   * @param array $names
   *   The tag names.
   *
   * @return string
   *   The Shortcode search regular expression
   */
//  protected function getShortcodeRegex($names) {
//    $regex_expression = implode('|', array_map('preg_quote', $names));
//
//    // WARNING! Do not change this regex without changing do_shortcode_tag()
//    // and strip_shortcodes().
//    return '(.?)\[(' . $regex_expression . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
//  }
}