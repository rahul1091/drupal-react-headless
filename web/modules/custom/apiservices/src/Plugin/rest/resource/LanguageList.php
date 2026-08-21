<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a Language List REST Resource.
 *
 * @RestResource(
 *   id = "language_list_rest",
 *   label = @Translation("Language List API"),
 *   uri_paths = {
 *     "canonical" = "/api/language-list"
 *   }
 * )
 */
class LanguageList extends ResourceBase {

  /**
   * Current logged-in user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $loggedUser;

  /**
   * Language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a new LanguageList object.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param array $serializer_formats
   *   Serializer formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );

    $this->loggedUser = $current_user;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('language_list_api'),
      $container->get('current_user'),
      $container->get('language_manager')
    );
  }

  /**
   * Returns the list of available languages.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response containing language data.
   */
  public function get() {
    try {
			$language_options = [];
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $langcode => $language) {
        $language_options[] = [
          'langcode' => $langcode,
					'name' => strtoupper($langcode),
          'lang_name' => $language->getName(),
        ];
      }

      return new JsonResponse([
        'status' => 'success',
        'result' => $language_options,
      ], 200);
    }
    catch (\Exception $exception) {
      return $this->exceptionErrorMsg($exception->getMessage());
    }
  }

  /**
   * Returns an error response.
   *
   * @param string $message
   *   Exception message.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Error response.
   */
  private function exceptionErrorMsg(string $message): JsonResponse {
    $this->logger->error($message);

    return new JsonResponse([
      'status' => 'error',
      'message' => 'An unexpected error occurred.',
      'error' => $message,
    ], 500);
  }
}
