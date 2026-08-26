<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Provides Project List API.
 *
 * @RestResource(
 *   id = "project_list_rest",
 *   label = @Translation("Project List API"),
 *   uri_paths = {
 *     "canonical" = "/api/project-list"
 *   }
 * )
 */
class ProjectList extends ResourceBase
{
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A current user instance which is logged in the session.
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   */
  public function __construct(
    array $config,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($config, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $config, $plugin_id, $plugin_definition)
  {
    return new static(
      $config,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('project_list_api'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * Get All Projects List API
   */
  public function get()
  {
    try {
      $is_anonymous = $this->currentUser->isAnonymous();
      $current_user_id = $this->currentUser->id();
      $current_user_name = $is_anonymous ? 'anonymous' : $this->currentUser->getAccountName();
      
      $account_fullname = '';
      if (!$is_anonymous) {
        $account = User::load($current_user_id);
        if ($account) {
          $account_fullname = trim(($account->get('field_firstname')->value ?? '') . ' ' . ($account->get('field_lastname')->value ?? ''));
        }
      }

      // Check if user is Super Admin
      $is_superadmin = (!$is_anonymous && $current_user_id == 1);

      // Load project details query
      $query = \Drupal::entityQuery('node')
        ->accessCheck(TRUE)
        ->condition('type', 'project_details')
        ->condition('status', 1)
        ->sort('created', 'DESC');

      // If anonymous or non-superadmin, handle restrictions. 
      // (By default, anonymous users will only see public projects matching access checks).
      if ($is_anonymous) {
        // Anonymous users can view published projects (accessCheck handles node permissions)
      } elseif (!$is_superadmin) {
        $query->condition('field_project_manager.target_id', $current_user_id);
      }

      $nids = $query->execute();
      $nodes = Node::loadMultiple($nids);
      $projects = [];

      foreach ($nodes as $node) {
        $project_manager = [];
        if (!$node->get('field_project_manager')->isEmpty()) {
          $user = $node->get('field_project_manager')->entity;
          if ($user) {
            $project_manager = [
              'uid' => $user->id(),
              'username' => $user->getAccountName(),
              'fullname' => trim(($user->get('field_firstname')->value ?? '') . ' ' . ($user->get('field_lastname')->value ?? '')),
            ];
          }
        }
        
        $projects[] = [
          'nid' => $node->id(),
          'project_id' => $node->id(),
          'project_name' => $node->getTitle(),
					'client_name' => $node->get('field_client_name')->value,
          'current_user_id' => $current_user_id,
          'current_user_name' => $current_user_name,
          'current_user_fullname' => $account_fullname,
          'project_manager' => $project_manager,
        ];
      }

      $message = 'Project List';
      if ($is_anonymous) {
        $message = 'Public Project List';
      } elseif ($is_superadmin) {
        $message = 'All Project List';
      } else {
        $message = 'Assigned Project List';
      }

      return new JsonResponse([
        'status' => 'Success',
        'message' => $message,
        'result' => $projects === [] ? 'No Projects Found.' : $projects,
      ]);
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
