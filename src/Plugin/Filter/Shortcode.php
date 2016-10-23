<?php

namespace Drupal\shortcode\Plugin\Filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\Url;
use Drupal\shortcode\Plugin\ShortcodeInterface;

/**
 * Provides a filter for insert view.
 *
 * @Filter(
 *   id = "shortcode",
 *   module = "shortcode",
 *   title = @Translation("Shortcodes"),
 *   description = @Translation("Provides WP like shortcodes to text formats."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class Shortcode extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $settings = array();
    //$this->settings += $defaults;

    /** @var \Drupal\shortcode\Shortcode\ShortcodeService $shortcodeService */
    $shortcodeService = \Drupal::service('shortcode');
    $shortcodes = $shortcodeService->getShortcodePlugins();

    /** @var \Drupal\Core\Plugin\DefaultPluginManager $type */
    $type = \Drupal::service('plugin.manager.shortcode');

    /** @var ShortcodeInterface $shortcode */
    foreach ($shortcodes as $plugin_id => $shortcode_info) {

      $shortcode = $type->createInstance($plugin_id);

      $description = $shortcode->getDescription();

      $settings[$plugin_id] = array(
        '#type' => 'checkbox',
        '#title' => $this->t('Enable %name shortcode', array('%name' => $shortcode->getLabel())),
        '#default_value' => NULL,
        '#description' => isset($description) ? $description : $this->t('Enable or disable this shortcode in this input format'),
      );

      if (!empty($this->settings[$plugin_id])) {
        $settings[$plugin_id]['#default_value'] = $this->settings[$plugin_id];
      }
      //elseif (!empty($defaults[$plugin_id])) {
      //  $settings[$key]['#default_value'] = $defaults[$plugin_id];
      //}
    }

    return $settings;

//
//    $form['shortcode'] = array(
//      '#type' => 'number',
//      '#title' => $this->t('Maximum link text length'),
//      '#default_value' => $this->settings['filter_url_length'],
//      '#min' => 1,
//      '#field_suffix' => $this->t('characters'),
//      '#description' => $this->t('URLs longer than this number of characters will be truncated to prevent long strings that break formatting. The link itself will be retained; just the text portion of the link will be truncated.'),
//    );
//    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (!empty($text)) {
      /** @var \Drupal\shortcode\Shortcode\ShortcodeService $shortcodeEngine */
      $shortcodeEngine = \Drupal::service('shortcode');
      $text = $shortcodeEngine->process($text, $langcode, $this);
    }

    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {

    // Get enabled shortcodes for a specific text format.

    /** @var \Drupal\shortcode\Shortcode\ShortcodeService $type */
    $type = \Drupal::service('shortcode');
    $shortcodes = $type->getShortcodePlugins($this);

    // Gather tips defined in all enabled plugins.
    $tips = array();

    /** @var \Drupal\shortcode\Shortcode\ShortcodePluginManager $type */
    $type = \Drupal::service('plugin.manager.shortcode');

    foreach ($shortcodes as $plugin_id => $shortcode_info) {
      /** @var \Drupal\shortcode\Plugin\ShortcodeInterface $shortcode */
      $shortcode = $type->createInstance($plugin_id);
      $tips[] = $shortcode->tips($long);
    }

    $output = '';
    foreach ($tips as $tip) {
      $output .= '<li>' . $tip . '</li>';
    }
    return '<p>You can use wp-like shortcodes such as: </p><ul>' . $output . '</ul>';
  }
}

