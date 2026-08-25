<?php

namespace Drupal\content_translate\Batch;

/**
 * Batch operations for translating a set of entities.
 */
class ContentTranslateBatch {

  /**
   * Batch operation: translate one entity into one target language.
   *
   * @param string $entity_type_id
   *   The entity type, e.g. 'node' or 'block_content'.
   * @param int|string $entity_id
   *   The entity ID.
   * @param string $source_langcode
   *   Source language code.
   * @param string $target_langcode
   *   Target language code.
   * @param bool $skip_existing
   *   Skip if a translation already exists in the target language.
   * @param array $context
   *   Batch context.
   */
  public static function processEntity($entity_type_id, $entity_id, $source_langcode, $target_langcode, $skip_existing, array &$context) {
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entity = $storage->load($entity_id);

    if (!$entity) {
      $context['results']['skipped'][] = "$entity_type_id:$entity_id (not found)";
      return;
    }

    if (!$entity instanceof \Drupal\Core\Entity\ContentEntityInterface) {
      $context['results']['skipped'][] = "$entity_type_id:$entity_id (not a content entity)";
      return;
    }

    if (!$entity->isTranslatable()) {
      $context['results']['skipped'][] = "$entity_type_id:$entity_id (bundle not configured as translatable)";
      return;
    }

    if ($skip_existing && $entity->hasTranslation($target_langcode)) {
      $context['results']['skipped'][] = "$entity_type_id:$entity_id ($target_langcode already exists)";
      return;
    }

    // Work from the source-language rendition of the entity if it exists.
    if ($entity->hasTranslation($source_langcode)) {
      $entity = $entity->getTranslation($source_langcode);
    }

    /** @var \Drupal\content_translate\ContentTranslatorService $translator_service */
    $translator_service = \Drupal::service('content_translate.translator_service');

    $success = $translator_service->translateEntity($entity, $source_langcode, $target_langcode);

    $label = method_exists($entity, 'label') ? $entity->label() : $entity_id;
    if ($success) {
      $context['results']['success'][] = "$entity_type_id:$entity_id ($label) -> $target_langcode";
    }
    else {
      $context['results']['failed'][] = "$entity_type_id:$entity_id ($label) -> $target_langcode";
    }

    $context['message'] = t('Translating %label into @lang...', [
      '%label' => $label,
      '@lang' => $target_langcode,
    ]);
  }

  /**
   * Batch finished callback.
   */
  public static function finished($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();

    $success_count = count($results['success'] ?? []);
    $failed_count = count($results['failed'] ?? []);
    $skipped_count = count($results['skipped'] ?? []);

    if ($success) {
      $messenger->addStatus(t('Translation batch complete: @s translated, @f failed, @k skipped.', [
        '@s' => $success_count,
        '@f' => $failed_count,
        '@k' => $skipped_count,
      ]));
    }
    else {
      $messenger->addError(t('The translation batch did not complete successfully.'));
    }

    if ($failed_count) {
      $messenger->addWarning(t('Some items failed to translate. Check the logs (admin/reports/dblog) for details.'));
    }
  }

}
