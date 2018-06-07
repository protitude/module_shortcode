<?php

namespace Drupal\shortcode\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;

/**
 * Provides a base class for Shortcode plugins.
 *
 * @see \Drupal\filter\Annotation\Filter
 * @see \Drupal\shortcode\Shortcode\ShortcodePluginManager
 * @see \Drupal\shortcode\Plugin\ShortcodeInterface
 * @see plugin_api
 */
abstract class ShortcodeBase extends PluginBase implements ShortcodeInterface {

  /**
   * The plugin ID of this filter.
   *
   * @var string
   */
  protected $plugin_id;

  /**
   * The name of the provider that owns this filter.
   *
   * @var string
   */
  public $provider;

  /**
   * A Boolean indicating whether this filter is enabled.
   *
   * @var bool
   */
  public $status = FALSE;

  /**
   * An associative array containing the configured settings of this filter.
   *
   * @var array
   */
  public $settings = array();

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->provider = $this->pluginDefinition['provider'];

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return array(
      'id' => $this->getPluginId(),
      'provider' => $this->pluginDefinition['provider'],
      'status' => $this->status,
      'settings' => $this->settings,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    if (isset($configuration['status'])) {
      $this->status = (bool) $configuration['status'];
    }
    if (isset($configuration['settings'])) {
      $this->settings = (array) $configuration['settings'];
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'provider' => $this->pluginDefinition['provider'],
      'status' => FALSE,
      'settings' => $this->pluginDefinition['settings'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->pluginDefinition['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // Implementations should work with and return $form. Returning an empty
    // array here allows the text format administration form to identify whether
    // this shortcode plugin has any settings form elements.
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
  }


  /**
   * Combines user attributes with known attributes.
   *
   * The $defaults should be considered to be all of the attributes which are
   * supported by the caller and given as a list. The returned attributes will
   * only contain the attributes in the $defaults list.
   *
   * If the $attributes list has unsupported attributes, they will be ignored and
   * removed from the final return list.
   *
   * @param array $defaults
   *   Entire list of supported attributes and their defaults.
   *
   * @param array $attributes
   *   User defined attributes in Shortcode tag.
   *
   * @return array
   *   Combined and filtered attribute list.
   */
  public function getAttributes($defaults, $attributes) {
    $attributes = (array) $attributes;
    $out = array();
    foreach ($defaults as $name => $default) {
      if (array_key_exists($name, $attributes)) {
        $out[$name] = $attributes[$name];
      }
      else {
        $out[$name] = $default;
      }
    }
    return $out;
  }

  /**
   * Add a class into a classes string if not already inside.
   *
   * @param mixed|string|array $classes
   *   The classes string or array.
   * @param string $new_class
   *   The class to add.
   *
   * @return string
   *   The proper classes string.
   */
  public function addClass($classes = '', $new_class = '') {
    if (empty($classes)) {
      $classes = [];
    }
    else if (!is_array($classes)) {
      $classes = explode(' ', Html::escape($classes));
    }

    $classes[] = Html::escape($new_class);
    $classes = array_unique($classes);

    return implode(' ', $classes);
  }

  /**
   * Returns a url to be used in a link element given path or url.
   *
   * If a path is supplied, an absolute url will be returned.
   * @param string $path
   *   The internal path to be translated.
   * @param bool $media_file_url
   *   TRUE If a media path is supplied, return the file url.
   */
  public function getUrlFromPath($path, $media_file_url = FALSE) {

    if ($path === '<front>') {
      $path = '/';
    }

    // Path validator. Return the path if an absolute url is detected.
    if ( UrlHelper::isValid($path, true) ) {
      return $path;
    }

    // Add a leading slash if not present.
    $path = '/' . ltrim($path, '/');

    if (!empty($media_file_url) && substr($path, 0, 6) === "/media") {
      $mid = $this->getMidFromPath( $path );
      if ($mid) {
        return $this->getMediaFileUrl($mid);
      }
    }
    else {
      /** @var \Drupal\Core\Path\AliasManager $alias_manager */
      $alias_manager = \Drupal::service('path.alias_manager');
      $alias = $alias_manager->getAliasByPath($path);
    }

    // Convert relative URL to absolute.
    $url = Url::fromUserInput($alias, array('absolute' => TRUE))->toString();

    return $url;
  }

  /**
   * Extracts the media id from a 'media/x' system path.
   *
   * @param string $path
   *   The internal path to be translated.
   * @return mixed|integer|bool
   *   The media id if found.
   */
  public function getMidFromPath( $path ) {
    if (preg_match('/media\/(\d+)/', $path, $matches)) {
      return $matches[1];
    }
    return false;
  }

  /**
   * Get the file url for a media object.
   *
   * @param integer $mid
   *   Media id.
   * @return mixed|integer|bool
   *   The media id if found.
   */
  public function getMediaFileUrl($mid) {
    $media_entity = \Drupal\media\Entity\Media::load($mid);
    $bundle = $media_entity->bundle();
    if ($bundle === 'file') {
      $field_media = $media_entity->get('field_media_file');
    }
    if ($bundle === 'image') {
      $field_media = $media_entity->get('field_media_image');
    }
    if ($bundle === 'video') {
      $field_media = $media_entity->get('field_media_video_file');
    }
    if ($bundle == 'audio') {
      $field_media = $media_entity->get('field_media_audio_file');
    }
    if ($field_media) {
      $file = $field_media->entity;
      return file_create_url($file->getFileUri());
    }
    return false;
  }

  /**
   * Returns the file entity for a given image media entity id.
   *
   * @param  integer $mid
   *   Media entity id.
   *
   * @return array
   *   File properties: `alt` and `path` where available.
   */
  public function getImageProperties($mid) {
    $properties = array(
      'alt' => '',
      'path' => ''
    );
    if (intval($mid)) {
      $media_entity = \Drupal\media\Entity\Media::load($mid);
    }
    if ($media_entity) {
      $field_media_image = $media_entity->get('field_media_image');
    }
    if ($field_media_image) {
      $properties['alt'] = $field_media_image->alt;
      $file = $field_media_image->entity;
    }
    if ( $file ) {
      $properties['path'] = $file->getFileUri();
    }
    return $properties;
  }

  /**
   * Returns a suitable title string given the user provided title and test.
   *
   * @param string $title
   *   The user provided title.
   * @param string $text
   *   The user provided text.
   *
   * @return string
   *   The title to be used.
   */
  public function getTitleFromAttributes($title, $text) {

    // Allow setting no title.
    if ($title === '<none>') {
      $title = '';
    }
    else {
      $title = empty($title) ? Html::escape($text) : Html::escape($title);
    }

    return $title;
  }

  /**
   * Wrapper for renderPlain.
   *
   * We use renderplain so that the shortcode's cache tags would not bubble up
   * to the parent and affect cacheability. Shortcode should be part of content
   * and self-container.
   *
   * @param $element
   * @return \Drupal\Component\Render\MarkupInterface|mixed
   */
  public function render(&$element) {
    /** @var Renderer $renderer */
    $renderer = \Drupal::service('renderer');
    return $renderer->renderPlain($element);
  }

}