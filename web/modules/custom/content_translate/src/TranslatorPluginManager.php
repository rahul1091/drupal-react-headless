<?php

namespace Drupal\content_translate;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages discovery of ContentTranslateTranslator plugins.
 *
 * Plugins live in src/Plugin/ContentTranslateTranslator. To add a new
 * translation provider in the future (Google, DeepL, Azure...), add a
 * new class there implementing TranslatorInterface - no other module
 * code needs to be modified.
 */
class TranslatorPluginManager extends DefaultPluginManager {

  /**
   * Constructs the manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ContentTranslateTranslator',
      $namespaces,
      $module_handler,
      'Drupal\content_translate\TranslatorInterface',
      'Drupal\content_translate\Annotation\ContentTranslateTranslator'
    );
    $this->alterInfo('content_translate_translator_info');
    $this->setCacheBackend($cache_backend, 'content_translate_translator_plugins');
  }

}
