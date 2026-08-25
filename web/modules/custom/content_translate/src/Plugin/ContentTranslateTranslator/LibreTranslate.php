<?php

namespace Drupal\content_translate\Plugin\ContentTranslateTranslator;

use Drupal\content_translate\TranslatorPluginBase;
use Drupal\content_translate\TranslationApiException;

/**
 * LibreTranslate provider.
 *
 * @ContentTranslateTranslator(
 *   id = "libretranslate",
 *   label = @Translation("LibreTranslate"),
 *   description = @Translation("Free / self-hostable open source translation API (libretranslate.com or your own instance).")
 * )
 */
class LibreTranslate extends TranslatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function translate($text, $source_langcode, $target_langcode, $is_html = FALSE) {
    if (trim($text) === '') {
      return $text;
    }

    $config = $this->getProviderConfig();
    $api_url = !empty($config['api_url']) ? $config['api_url'] : 'https://libretranslate.com/translate';
    $api_key = $config['api_key'] ?? '';

    $payload = [
      'q' => $text,
      'source' => $source_langcode,
      'target' => $target_langcode,
      'format' => $is_html ? 'html' : 'text',
    ];
    if (!empty($api_key)) {
      $payload['api_key'] = $api_key;
    }

    try {
      $response = $this->httpClient->request('POST', $api_url, [
        'json' => $payload,
        'headers' => ['Accept' => 'application/json'],
        'timeout' => 15,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);

      if (!empty($data['translatedText'])) {
        return $data['translatedText'];
      }

      throw new TranslationApiException('LibreTranslate returned an unexpected response: ' . (string) $response->getBody());
    }
    catch (\Exception $e) {
      throw new TranslationApiException('LibreTranslate request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $current_config) {
    $form['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('LibreTranslate API URL'),
      '#description' => $this->t('E.g. %default for the public instance, or the URL of your own self-hosted instance.', ['%default' => 'https://libretranslate.com/translate']),
      '#default_value' => $current_config['api_url'] ?? 'https://libretranslate.com/translate',
      '#required' => TRUE,
    ];
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('Optional. Required by the public libretranslate.com instance for most usage; not needed for many self-hosted instances.'),
      '#default_value' => $current_config['api_key'] ?? '',
    ];
    return $form;
  }

}
