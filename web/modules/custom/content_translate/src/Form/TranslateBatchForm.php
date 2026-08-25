<?php

namespace Drupal\content_translate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\content_translate\TranslatableFieldHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin page: choose entity type(s)/bundle(s), source & target language,
 * and run a batch translation job.
 */
class TranslateBatchForm extends FormBase {

  /**
   * Entity types this module knows how to translate.
   *
   * Add more here (e.g. 'taxonomy_term', 'media') as needed - as long as
   * the entity type is a content entity with translation enabled.
   *
   * @var array
   */
  protected static $supportedEntityTypes = [
    'node' => 'Content (nodes)',
    'block_content' => 'Custom blocks',
  ];

  /**
   * Constructs the form.
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected TranslatableFieldHelper $fieldHelper,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('content_translate.field_helper'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_translate_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $languages = $this->languageManager->getLanguages();
    $language_options = [];
    foreach ($languages as $langcode => $language) {
      $language_options[$langcode] = $language->getName();
    }
    $default_langcode = $this->languageManager->getDefaultLanguage()->getId();

    if (count($languages) < 2) {
      $this->messenger()->addWarning($this->t('Only one language is configured on this site. Add additional languages at <a href=":url">the languages page</a> before running translations.', [
        ':url' => '/admin/config/regional/language',
      ]));
    }

    $form['#tree'] = FALSE;

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content to translate'),
      '#description' => $this->t('Select whether to translate nodes or custom blocks.'),
      '#options' => static::$supportedEntityTypes,
      '#default_value' => $form_state->getValue('entity_type', 'node'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateBundleOptions',
        'wrapper' => 'bundle-options-wrapper',
      ],
    ];

    $selected_entity_type = $form_state->getValue('entity_type', 'node');

    $form['bundles_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'bundle-options-wrapper'],
    ];
    $form['bundles_wrapper']['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types / block types'),
      '#description' => $this->t('Select one or more bundles to include in this batch.'),
      '#options' => $this->fieldHelper->getBundles($selected_entity_type),
      '#required' => TRUE,
    ];

    $form['languages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Languages'),
    ];

    $form['languages']['source_langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Translate from'),
      '#options' => $language_options,
      '#default_value' => $default_langcode,
      '#required' => TRUE,
    ];

    $form['languages']['target_langcodes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Translate into'),
      '#description' => $this->t('Select one or more target languages listed on this site.'),
      '#options' => $language_options,
      '#required' => TRUE,
    ];

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Options'),
    ];

    $form['options']['skip_existing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip items that already have a translation in the target language'),
      '#default_value' => TRUE,
    ];

    $form['options']['published_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Only translate published content'),
      '#default_value' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="entity_type"]' => ['value' => 'node'],
        ],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run batch translation'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Ajax callback: refresh bundle checkboxes for the selected entity type.
   */
  public function updateBundleOptions(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $form['bundles_wrapper']['bundles']['#options'] = $this->fieldHelper->getBundles($entity_type);
    return $form['bundles_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $targets = array_filter($form_state->getValue('target_langcodes', []));
    $source = $form_state->getValue('source_langcode');

    if (isset($targets[$source])) {
      $form_state->setErrorByName('target_langcodes', $this->t('The target language list cannot include the source language.'));
    }

    if (empty($targets)) {
      $form_state->setErrorByName('target_langcodes', $this->t('Select at least one target language.'));
    }

    $bundles = array_filter($form_state->getValue('bundles', []));
    if (empty($bundles)) {
      $form_state->setErrorByName('bundles', $this->t('Select at least one content type / block type.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type_id = $form_state->getValue('entity_type');
    $bundles = array_keys(array_filter($form_state->getValue('bundles', [])));
    $source_langcode = $form_state->getValue('source_langcode');
    $target_langcodes = array_keys(array_filter($form_state->getValue('target_langcodes', [])));
    $skip_existing = (bool) $form_state->getValue('skip_existing');
    $published_only = (bool) $form_state->getValue('published_only');

    // Gather entity IDs to process.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery()->accessCheck(TRUE);
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('bundle');
    if ($bundle_key) {
      $query->condition($bundle_key, $bundles, 'IN');
    }
    if ($published_only && $entity_type_id === 'node') {
      $query->condition('status', 1);
    }
    $ids = $query->execute();

    if (empty($ids)) {
      $this->messenger()->addWarning($this->t('No matching content found for the selected type(s).'));
      return;
    }

    $batch_builder = (new \Drupal\Core\Batch\BatchBuilder())
      ->setTitle($this->t('Translating content'))
      ->setInitMessage($this->t('Starting translation batch...'))
      ->setProgressMessage($this->t('Processed @current out of @total.'))
      ->setErrorMessage($this->t('An error occurred during translation.'))
      ->setFinishCallback([\Drupal\content_translate\Batch\ContentTranslateBatch::class, 'finished']);

    foreach ($ids as $id) {
      foreach ($target_langcodes as $target_langcode) {
        $batch_builder->addOperation(
          [\Drupal\content_translate\Batch\ContentTranslateBatch::class, 'processEntity'],
          [$entity_type_id, $id, $source_langcode, $target_langcode, $skip_existing]
        );
      }
    }

    batch_set($batch_builder->toArray());
  }

}
