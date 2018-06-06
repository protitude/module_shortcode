<?php
/**
 * @file
 * Contains \Drupal\shortcode\Plugin\Shortcode\ImageShortcode.
 */

namespace Drupal\shortcode\Plugin\Shortcode;

use Drupal\Core\Language\Language;
use Drupal\shortcode\Plugin\ShortcodeBase;

/**
 * The image shortcode.
 *
 * @Shortcode(
 *   id = "img",
 *   title = @Translation("Image"),
 *   description = @Translation("Show an image.")
 * )
 */
class ImageShortcode extends ShortcodeBase {

  /**
   * {@inheritdoc}
   */
  public function process($attributes, $text, $langcode = Language::LANGCODE_NOT_SPECIFIED) {

    // Merge with default attributes.
    $attributes = $this->getAttributes(array(
      'class' => '',
      'alt' => '',
      'src' => '',
      'mid' => '',
      'imagestyle' => ''
    ),
      $attributes
    );

    $class = $this->addClass($attributes['class'], 'img');

    if ($attributes['mid']) {
      $properties = $this->getImageProperties($attributes['mid']);
      if ($properties['path']) {
        if ($attributes['imagestyle']) {
          $attributes['src'] = \Drupal\image\Entity\ImageStyle::load($attributes['imagestyle'])->buildUrl($properties['path']);
        }
        else {
          $attributes['src'] = file_create_url($properties['path']);
        }
      }
      if ($properties['alt'] && !$attributes['alt']) {
        $attributes['alt'] = $properties['alt'];
      }
    }

    $output = array(
      '#theme' => 'shortcode_img',
      '#src' => $attributes['src'],
      '#class' => $class,
      '#alt' => $attributes['alt'],
    );

    return $this->render($output);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    $output = array();
    $output[] = '<p><strong>' . $this->t('[img (src="image.jpg"|mid="1") (class="additional class"|alt="alt text"|imagestyle="medium")/]') . '</strong> ';
    $output[] = $this->t('Inserts an image based on the given image url or media id. If media id is supplied with no alt text, the alt text from the media object will be applied.') . '</p>';
    return implode(' ', $output);
  }
}