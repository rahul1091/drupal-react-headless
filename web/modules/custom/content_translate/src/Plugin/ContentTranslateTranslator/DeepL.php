<?php

namespace Drupal\content_translate\Plugin\ContentTranslateTranslator;

use Drupal\content_translate\TranslatorPluginBase;
use Drupal\content_translate\TranslationApiException;

/**
 * DeepL provider (Free or Pro API).
 *
 * Free tier: DeepL API Free offers a monthly free character quota; keys
 * for the free plan end in ":fx" and use the api-free.deepl.com host,
 * while Pro keys use api.deepl.com. Check current terms at
 * https://www.deepl.com/pro-api.
 *
 * @ContentTranslateTranslator(
 *   id = "deepl",
 *   label = @Translation("DeepL"),
 *   description = @Translation("DeepL API (Free or Pro). High translation quality; free tier has a monthly character quota.")
 * )
 */
class DeepL extends TranslatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function translate($text, $source_langcode, $target_langcode, $is_html = FALSE) {
    if (trim($text) === '') {
      return $text;
    }

    $config = $this->getProviderConfig();
    $api_key = $config['api_key'] ?? '';
    $plan = $config['plan'] ?? 'free';

    if (empty($api_key)) {
      throw new TranslationApiException('DeepL API key is not configured.');
    }

    $endpoint = $plan === 'pro'
      ? 'https://api.deepl.com/v2/translate'
      : 'https://api-free.deepl.com/v2/translate';

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Authorization' => 'DeepL-Auth-Key ' . $api_key,
          'Accept' => 'application/json',
        ],
        'form_params' => [
          'text' => $text,
          'source_lang' => strtoupper($source_langcode),
          'target_lang' => strtoupper($target_langcode),
          'tag_handling' => $is_html ? 'html' : '',
        ],
        'timeout' => 15,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);

      if (!empty($data['translations'][0]['text'])) {
        return $data['translations'][0]['text'];
      }

      throw new TranslationApiException('DeepL returned an unexpected response: ' . (string) $response->getBody());
    }
    catch (\Exception $e) {
      throw new TranslationApiException('DeepL request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $current_config) {
    $form['plan'] = [
      '#type' => 'select',
      '#title' => $this->t('DeepL plan'),
      '#options' => [
        'free' => $this->t('Free (api-free.deepl.com)'),
        'pro' => $this->t('Pro (api.deepl.com)'),
      ],
      '#default_value' => $current_config['plan'] ?? 'free',
      '#required' => TRUE,
    ];
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DeepL API key'),
      '#description' => $this->t('Free-plan keys typically end in ":fx". Get one at <a href=":url" target="_blank" rel="noopener">deepl.com/pro-api</a>.', [
        ':url' => 'https://www.deepl.com/pro-api',
      ]),
      '#default_value' => $current_config['api_key'] ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }

}
