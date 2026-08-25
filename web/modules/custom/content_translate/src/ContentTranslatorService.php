<?php

namespace Drupal\content_translate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Translates entities using the currently configured translator plugin.
 */
class ContentTranslatorService {

  /**
   * Constructs the service.
   */
  public function __construct(
    protected TranslatorPluginManager $pluginManager,
    protected ConfigFactoryInterface $configFactory,
    protected TranslatableFieldHelper $fieldHelper,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Gets a human-readable list of available translator providers.
   *
   * @return array
   *   Plugin ID => label, for use in a select list.
   */
  public function getAvailableProviders() {
    $options = [];
    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      $options[$id] = $definition['label'];
    }
    return $options;
  }

  /**
   * Gets the currently active translator plugin instance.
   *
   * @return \Drupal\content_translate\TranslatorInterface
   *   The active provider plugin.
   */
  public function getActiveTranslator() {
    $active_id = $this->configFactory->get('content_translate.settings')->get('active_translator') ?: 'libretranslate';
    return $this->pluginManager->createInstance($active_id);
  }

  /**
   * Translates and saves a translation for one content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity (in the source language).
   * @param string $source_langcode
   *   Source language code.
   * @param string $target_langcode
   *   Target language code.
   *
   * @return bool
   *   TRUE on success, FALSE if translation failed for this entity.
   */
  public function translateEntity(ContentEntityInterface $entity, $source_langcode, $target_langcode) {
    $logger = $this->loggerFactory->get('content_translate');
    $translator = $this->getActiveTranslator();

    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $fields = $this->fieldHelper->getTranslatableFields($entity_type_id, $bundle);

    if (empty($fields)) {
      $logger->notice('No translatable fields found for @type / @bundle (@id).', [
        '@type' => $entity_type_id,
        '@bundle' => $bundle,
        '@id' => $entity->id(),
      ]);
      return FALSE;
    }

    // Get or create the translation object for the target language. Seed
    // it with the source entity's current values so that any field we
    // don't touch (or that fails to translate) still has valid content,
    // rather than being left blank and violating required-field
    // constraints (e.g. node title).
    if ($entity->hasTranslation($target_langcode)) {
      $translation = $entity->getTranslation($target_langcode);
    }
    else {
      $translation = $entity->addTranslation($target_langcode, $entity->toArray());
    }

    $had_error = FALSE;

    foreach ($fields as $field_name => $is_html) {
      if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
        continue;
      }

      try {
        if ($field_name === 'title' || $translation->getFieldDefinition($field_name)->getType() === 'string') {
          // Plain string field (e.g. node title).
          $value = $entity->get($field_name)->value;
          $translated = $translator->translate($value, $source_langcode, $target_langcode, FALSE);
          $translation->set($field_name, $translated);
          continue;
        }

        // Multi-value / formatted text fields: translate each delta.
        $items = $entity->get($field_name);
        $new_values = [];
        foreach ($items as $delta => $item) {
          $item_values = $item->getValue();
          if (isset($item_values['value'])) {
            $item_values['value'] = $translator->translate($item_values['value'], $source_langcode, $target_langcode, $is_html);
          }
          // text_with_summary has an optional 'summary' key too.
          if (!empty($item_values['summary'])) {
            $item_values['summary'] = $translator->translate($item_values['summary'], $source_langcode, $target_langcode, $is_html);
          }
          $new_values[$delta] = $item_values;
        }
        $translation->set($field_name, $new_values);
      }
      catch (TranslationApiException $e) {
        $had_error = TRUE;
        // Preserve the original (untranslated) value rather than leaving
        // the field blank, which could violate required-field
        // constraints (e.g. node title) and break the save.
        $translation->set($field_name, $entity->get($field_name)->getValue());
        $logger->error('Failed translating field @field on @type @id: @message', [
          '@field' => $field_name,
          '@type' => $entity_type_id,
          '@id' => $entity->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    if ($had_error) {
      // Still save what succeeded, but let the caller know something failed.
      $translation->save();
      return FALSE;
    }

    $translation->save();
    return TRUE;
  }

}
