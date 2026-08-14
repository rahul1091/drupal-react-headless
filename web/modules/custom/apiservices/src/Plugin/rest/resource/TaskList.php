<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a REST resource for managing Project Tracker tasks.
 *
 * @RestResource(
 *   id = "tasklist_rest",
 *   label = @Translation("Task List API"),
 *   uri_paths = {
 *     "canonical" = "/api/task-list",
 *     "create" = "/api/add-task"
 *   }
 * )
 */
class TaskList extends ResourceBase
{

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new TaskList instance.
   *
   * @param array $config
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    array $config,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($config, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('logger.factory')->get('task_list_api'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests to fetch Project Tracker tasks.
   * Route: GET /api/tasks?_format=json
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON list of tasks.
   */
  public function get()
  {
    try {
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      $nids = $nodeStorage->getQuery()
        ->condition('type', 'project_tracker')
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->accessCheck(FALSE)
        ->execute();

      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $nodeStorage->loadMultiple($nids);
      $tasks = [];

      foreach ($nodes as $node) {
        $tasks[] = [
          'id' => $node->id(),
          'title' => $node->getTitle(),
          'description' => $node->hasField('field_description') ? $node->get('field_description')->value : '',
          'due_date' => $node->hasField('field_due_date') ? $node->get('field_due_date')->value : '',
          'severity' => $node->hasField('field_severity') ? $node->get('field_severity')->value : '',
          'status' => $node->hasField('field_status') ? $node->get('field_status')->value : '',
        ];
      }

      return new JsonResponse([
        'status' => 'Success',
        'result' => $tasks,
      ], 200);
    } catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return new JsonResponse([
        'status' => 'Error',
        'message' => 'An unexpected error occurred while fetching tasks.',
        'error' => $exception->getMessage(),
      ], 500);
    }
  }

  public function post(Request $request)
  {
    try {
      $content = $request->getContent();
      $data = json_decode($content, TRUE);

      $title = trim($data['title'] ?? '');
      $description = trim($data['description'] ?? '');
      $dueDate = trim($data['due_date'] ?? '');
      $severity = trim($data['severity'] ?? 'Low');
      $status = trim($data['status'] ?? 'Open');

      // Validation check for required fields
      if (empty($title) || empty($description) || empty($dueDate)) {
        return new JsonResponse([
          'status' => 'Error',
          'message' => 'Missing required fields: Title, Description, and Due Date are mandatory.',
        ], 400);
      }

      // Create new Project Tracker node programmatically
      $node = Node::create([
        'type' => 'project_tracker',
        'title' => $title,
        'field_description' => $description,
        'field_due_date' => $dueDate,
        'field_severity' => $severity,
        'field_status' => $status,
        'status' => 1, // Published
      ]);

      $node->save();

      return new JsonResponse([
        'status' => 'Success',
        'message' => 'Task created successfully.',
        'result' => [
          'id' => $node->id(),
          'title' => $node->getTitle(),
          'field_description' => $description,
          'field_due_date' => $dueDate,
          'field_severity' => $severity,
          'field_status' => $status,
        ],
      ], 201);
    } catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return new JsonResponse([
        'status' => 'Error',
        'message' => 'Failed to create task.',
        'error' => $exception->getMessage(),
      ], 500);
    }
  }
}
