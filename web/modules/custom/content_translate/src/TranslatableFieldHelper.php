<?php

namespace Drupal\content_translate;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Determines which fields of an entity should be auto-translated.
 *
 * Text-bearing fields (title, body, plain/formatted text fields) are
 * translated. Fields like images, files, entity references (including
 * taxonomy term references), and other machine/structural fields are
 * always skipped.
 */
class TranslatableFieldHelper {

  /**
   * Field types eligible for automatic text translation.
   *
   * @var string[]
   */
  protected static $translatableFieldTypes = [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ];

  /**
   * Field types that carry HTML markup that must be preserved.
   *
   * @var string[]
   */
  protected static $htmlFieldTypes = [
    'text',
    'text_long',
    'text_with_summary',
  ];

  /**
   * Base fields that should never be translated even if type matches.
   *
   * @var string[]
   */
  protected static $excludedFieldNames = [
    'uuid',
    'vid',
    'nid',
    'uid',
    'type',
    'langcode',
    'status',
    'created',
    'changed',
    'promote',
    'sticky',
    'path',
    'menu_link',
    // Block content admin label - not front-end content, skip by default.
    'info',
  ];

  /**
   * Constructs the helper.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  /**
   * Gets the list of field names on a bundle that should be translated.
   *
   * @param string $entity_type_id
   *   E.g. 'node' or 'block_content'.
   * @param string $bundle
   *   The bundle/content type machine name.
   *
   * @return array
   *   Array keyed by field name, value TRUE if it carries HTML.
   */
  public function getTranslatableFields($entity_type_id, $bundle) {
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    $fields = [];

    foreach ($definitions as $field_name => $definition) {
      if (in_array($field_name, static::$excludedFieldNames, TRUE)) {
        continue;
      }

      // 'title' on nodes is a base field of type 'string' - always allow it.
      $type = $definition->getType();
      if (!in_array($type, static::$translatableFieldTypes, TRUE)) {
        continue;
      }

      // Skip fields that aren't actually configured as translatable on
      // the entity (site builder may have opted a field out).
      if (method_exists($definition, 'isTranslatable') && $definition->isTranslatable() === FALSE && $field_name !== 'title') {
        continue;
      }

      $fields[$field_name] = in_array($type, static::$htmlFieldTypes, TRUE);
    }

    return $fields;
  }

  /**
   * Returns bundles (content types / block types) for an entity type.
   *
   * @param string $entity_type_id
   *   'node' or 'block_content'.
   *
   * @return array
   *   Bundle machine name => label.
   */
  public function getBundles($entity_type_id) {
    $bundles = $this->bundleInfo->getBundleInfo($entity_type_id);
    $options = [];
    foreach ($bundles as $id => $info) {
      $options[$id] = $info['label'];
    }
    return $options;
  }

}
