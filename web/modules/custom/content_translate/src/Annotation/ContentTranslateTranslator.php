<?php

namespace Drupal\content_translate\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ContentTranslateTranslator annotation object.
 *
 * Plugins of this type wrap a translation API/provider (LibreTranslate,
 * Google, DeepL, Azure, etc). New providers can be added by creating a
 * new plugin class annotated with this and implementing
 * \Drupal\content_translate\TranslatorInterface. No other module code
 * needs to change to support a new provider.
 *
 * @Annotation
 */
class ContentTranslateTranslator extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the translator provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
