<?php

namespace Drupal\content_translate;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Base class for ContentTranslateTranslator plugins.
 */
abstract class TranslatorPluginBase extends PluginBase implements TranslatorInterface, ContainerFactoryPluginInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the plugin.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /**
   * Gets this provider's saved configuration.
   *
   * @return array
   *   The provider-specific settings array.
   */
  protected function getProviderConfig() {
    $config = $this->configFactory->get('content_translate.settings');
    return $config->get('translators.' . $this->getPluginId()) ?: [];
  }

}
