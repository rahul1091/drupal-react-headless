<?php

namespace Drupal\content_translate\Plugin\ContentTranslateTranslator;

use Drupal\content_translate\TranslatorPluginBase;
use Drupal\content_translate\TranslationApiException;

/**
 * Google Cloud Translation (Basic, v2) provider.
 *
 * Free tier: Google offers a monthly free quota (check current terms at
 * https://cloud.google.com/translate/pricing). Requires a Google Cloud
 * project with the Cloud Translation API enabled and an API key.
 *
 * @ContentTranslateTranslator(
 *   id = "google_translate",
 *   label = @Translation("Google Cloud Translation"),
 *   description = @Translation("Google Cloud Translation API v2 (API key auth). Has a free monthly quota; usage beyond it is billed.")
 * )
 */
class GoogleTranslate extends TranslatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function translate($text, $source_langcode, $target_langcode, $is_html = FALSE) {
    if (trim($text) === '') {
      return $text;
    }

    $config = $this->getProviderConfig();
    $api_key = $config['api_key'] ?? '';

    if (empty($api_key)) {
      throw new TranslationApiException('Google Cloud Translation API key is not configured.');
    }

    $endpoint = 'https://translation.googleapis.com/language/translate/v2';

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'query' => ['key' => $api_key],
        'json' => [
          'q' => $text,
          'source' => $source_langcode,
          'target' => $target_langcode,
          'format' => $is_html ? 'html' : 'text',
        ],
        'headers' => ['Accept' => 'application/json'],
        'timeout' => 15,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);

      if (!empty($data['data']['translations'][0]['translatedText'])) {
        return $data['data']['translations'][0]['translatedText'];
      }

      throw new TranslationApiException('Google Translate returned an unexpected response: ' . (string) $response->getBody());
    }
    catch (\Exception $e) {
      throw new TranslationApiException('Google Translate request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $current_config) {
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Cloud API key'),
      '#description' => $this->t('An API key from a Google Cloud project with the Cloud Translation API enabled. See <a href=":url" target="_blank" rel="noopener">cloud.google.com/translate/docs/setup</a>.', [
        ':url' => 'https://cloud.google.com/translate/docs/setup',
      ]),
      '#default_value' => $current_config['api_key'] ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }

}
