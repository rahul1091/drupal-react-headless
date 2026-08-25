<?php

namespace Drupal\content_translate\Plugin\ContentTranslateTranslator;

use Drupal\content_translate\TranslatorPluginBase;
use Drupal\content_translate\TranslationApiException;

/**
 * Microsoft Azure AI Translator provider.
 *
 * Free tier: Azure offers a free (F0) pricing tier with a monthly
 * character quota. Requires an Azure AI services / Translator resource,
 * its key, and its region. Check current terms at
 * https://azure.microsoft.com/en-us/pricing/details/cognitive-services/translator/.
 *
 * @ContentTranslateTranslator(
 *   id = "azure_translator",
 *   label = @Translation("Azure AI Translator"),
 *   description = @Translation("Microsoft Azure AI Translator. Has a free (F0) monthly quota tier.")
 * )
 */
class AzureTranslator extends TranslatorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function translate($text, $source_langcode, $target_langcode, $is_html = FALSE) {
    if (trim($text) === '') {
      return $text;
    }

    $config = $this->getProviderConfig();
    $api_key = $config['api_key'] ?? '';
    $region = $config['region'] ?? '';
    $endpoint = !empty($config['endpoint']) ? rtrim($config['endpoint'], '/') : 'https://api.cognitive.microsofttranslator.com';

    if (empty($api_key) || empty($region)) {
      throw new TranslationApiException('Azure Translator API key and region must be configured.');
    }

    try {
      $response = $this->httpClient->request('POST', $endpoint . '/translate', [
        'query' => [
          'api-version' => '3.0',
          'from' => $source_langcode,
          'to' => $target_langcode,
          'textType' => $is_html ? 'html' : 'plain',
        ],
        'headers' => [
          'Ocp-Apim-Subscription-Key' => $api_key,
          'Ocp-Apim-Subscription-Region' => $region,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => [
          ['Text' => $text],
        ],
        'timeout' => 15,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);

      if (!empty($data[0]['translations'][0]['text'])) {
        return $data[0]['translations'][0]['text'];
      }

      throw new TranslationApiException('Azure Translator returned an unexpected response: ' . (string) $response->getBody());
    }
    catch (\Exception $e) {
      throw new TranslationApiException('Azure Translator request failed: ' . $e->getMessage(), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array $current_config) {
    $form['endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('API endpoint'),
      '#description' => $this->t('Leave as default unless you have a sovereign/regional Azure endpoint.'),
      '#default_value' => $current_config['endpoint'] ?? 'https://api.cognitive.microsofttranslator.com',
      '#required' => TRUE,
    ];
    $form['region'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource region'),
      '#description' => $this->t('E.g. "eastus" - the region your Azure Translator resource was created in.'),
      '#default_value' => $current_config['region'] ?? '',
      '#required' => TRUE,
    ];
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('One of the keys from your Azure AI services / Translator resource.'),
      '#default_value' => $current_config['api_key'] ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }

}
