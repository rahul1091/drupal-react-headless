<?php

namespace Drupal\content_translate;

/**
 * Interface for translation provider plugins.
 *
 * Implement this (plus the ContentTranslateTranslator annotation) to add
 * support for a new translation API alongside LibreTranslate, e.g. Google
 * Cloud Translation, DeepL, Azure Translator, etc.
 */
interface TranslatorInterface {

  /**
   * Translates a single string of text.
   *
   * @param string $text
   *   The source text. May contain simple inline HTML markup.
   * @param string $source_langcode
   *   The source language code (e.g. 'en').
   * @param string $target_langcode
   *   The target language code (e.g. 'fr').
   * @param bool $is_html
   *   Whether $text contains HTML markup that should be preserved.
   *
   * @return string
   *   The translated text. If translation fails, the original text is
   *   returned unchanged and an error is logged by the caller.
   *
   * @throws \Drupal\content_translate\TranslationApiException
   *   Thrown when the remote API call fails.
   */
  public function translate($text, $source_langcode, $target_langcode, $is_html = FALSE);

  /**
   * Returns the plugin's configuration form elements.
   *
   * Used by SettingsForm to render provider-specific settings
   * (API URL, API key, etc) without SettingsForm needing to know about
   * every provider individually.
   *
   * @param array $form
   *   The parent form array.
   * @param array $current_config
   *   Current saved configuration for this provider.
   *
   * @return array
   *   A render array of form elements, keyed by config key.
   */
  public function buildConfigurationForm(array $form, array $current_config);

}
