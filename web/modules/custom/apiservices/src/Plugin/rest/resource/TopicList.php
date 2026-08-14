<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;

/**
 * Provides a resource to get view modes by entity and bundle.
 * @RestResource(
 *   id = "topiclist_rest",
 *   label = @Translation("Topiclist API"),
 *   uri_paths = {
 *     "canonical" = "/api/topiclist",
 *   }
 * )
 */

class TopicList extends ResourceBase
{
  /**
   * A current user instance which is logged in the session.
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $loggedUser;
  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $config
   *   A configuration array which contains the information about the plugin instance.
   * @param string $module_id
   *   The module_id for the plugin instance.
   * @param mixed $module_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A currently logged user instance.
   */

  public function __construct(array $config, $module_id, $module_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user)
  {
    parent::__construct($config, $module_id, $module_definition, $serializer_formats, $logger);
    $this->loggedUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $config, $module_id, $module_definition)
  {
    return new static(
      $config,
      $module_id,
      $module_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('topiclist_api'),
      $container->get('current_user')
    );
  }

  /*
    * Get All Topiclist API
    */
  public function get()
  {
    try {
      $project_nids = \Drupal::entityQuery('node')
        ->accessCheck(TRUE)
        ->condition('type', 'landing_page')
        ->condition('status', 1)
        ->execute();

      $project_nodes = Node::loadMultiple($project_nids);

      $project_list_data = [];

      foreach ($project_nodes as $key => $node) {
        $project_list_data[] = [
          'id' => $node->id(),
          'title' => $node->getTitle(),
          'subheading' => $node->get('field_sub_heading')->value,
          'description' => $node->get('field_description')->value,
          'trending' => $node->get('field_trending')->value,
        ];
      }

      $final_api_reponse = array(
        "status" => "Success",
        "message" => "Topic List",
        "result" => $project_list_data
      );
      return new JsonResponse($final_api_reponse);
    } catch (\Exception $exception) {
      return $this->exception_error_msg($exception->getMessage());
    }
  }

  /**
   * Returns a JSON error response for an exception.
   *
   * @param string $message
   *   The exception message.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  private function exception_error_msg($message)
  {
    $this->logger->error($message);
    return new JsonResponse([
      'status' => 'Error',
      'message' => 'An unexpected error occurred.',
      'error' => $message,
    ], 500);
  }
}
