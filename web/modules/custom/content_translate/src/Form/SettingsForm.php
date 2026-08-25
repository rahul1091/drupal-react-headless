<?php

namespace Drupal\content_translate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\content_translate\TranslatorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form: pick which translation API provider is active and
 * configure its credentials. Extensible - new provider plugins appear
 * here automatically.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Constructs the form.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, protected TranslatorPluginManager $pluginManager) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.content_translate_translator')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['content_translate.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_translate_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('content_translate.settings');

    $provider_options = [];
    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      $provider_options[$id] = $definition['label'];
    }

    $active_provider = $form_state->getValue('active_translator', $config->get('active_translator'));

    $form['active_translator'] = [
      '#type' => 'select',
      '#title' => $this->t('Translation API provider'),
      '#description' => $this->t('Additional providers can be added later as plugins (e.g. Google Cloud Translation, DeepL, Azure Translator) without changing this form.'),
      '#options' => $provider_options,
      '#default_value' => $active_provider,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateProviderSettings',
        'wrapper' => 'provider-settings-wrapper',
      ],
    ];

    $form['provider_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Provider settings'),
      '#prefix' => '<div id="provider-settings-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    if ($active_provider) {
      $plugin = $this->pluginManager->createInstance($active_provider);
      $current_config = $config->get('translators.' . $active_provider) ?: [];
      $form['provider_settings'] += $plugin->buildConfigurationForm([], $current_config);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback: rebuild the provider-specific settings fieldset.
   */
  public function updateProviderSettings(array &$form, FormStateInterface $form_state) {
    return $form['provider_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $active_translator = $form_state->getValue('active_translator');
    $provider_values = $form_state->getValue('provider_settings') ?: [];

    $config = $this->config('content_translate.settings');
    $config->set('active_translator', $active_translator);
    $config->set('translators.' . $active_translator, $provider_values);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
