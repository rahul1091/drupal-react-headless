<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
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
	 * The current user session.
	 *
	 * @var \Drupal\Core\Session\AccountProxyInterface
	 */
	protected $currentUser;

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
	 * @param \Drupal\Core\Session\AccountProxyInterface $current_user
	 *   The current user session.
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
			$container->get('logger.factory')->get('task_list_api'),
			$container->get('entity_type.manager'),
			$container->get('current_user')
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
		// Task assignment is per-user, so an anonymous caller has no
		// meaningful "my tasks" to return.
		if ($this->currentUser->isAnonymous()) {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Authentication required to view tasks.',
			], 403);
		}

		try {
			$nodeStorage = $this->entityTypeManager->getStorage('node');
			$isSuperAdmin = ((int) $this->currentUser->id() === 1);

			// Check if the current user has the 'administrator' role
			$isAdminRole = in_array('administrator', $this->currentUser->getRoles());

			$query = $nodeStorage->getQuery()
				->condition('type', 'project_tracker')
				->condition('status', 1)
				->sort('created', 'DESC')
				->accessCheck(FALSE);

			// Superadmin (uid=1) or users with 'administrator' role can see all tasks across all users.
			// Other authenticated users only see tasks assigned specifically to them.
			if (!$isSuperAdmin && !$isAdminRole) {
				$query->condition('field_assigned_to', $this->currentUser->id());
			}

			$nids = $query->execute();

			/** @var \Drupal\node\NodeInterface[] $nodes */
			$nodes = $nodeStorage->loadMultiple($nids);
			$tasks = [];

			foreach ($nodes as $node) {
				$project_id = $node->hasField('field_project_id') ? $node->get('field_project_id')->target_id : NULL;
				$project_name = '';
				if ($project_id) {
					$project = Node::load($project_id);
					if ($project) {
						$project_name = $project->getTitle();
					}
				}

				$tasks[] = [
					'id' => $node->id(),
					'project_id' => $project_id,
					'project_name' => $project_name,
					'title' => $node->getTitle(),
					'description' => $node->hasField('field_description') ? $node->get('field_description')->value : '',
					'due_date' => $node->hasField('field_due_date') ? $node->get('field_due_date')->value : '',
					'severity' => $node->hasField('field_severity') ? $node->get('field_severity')->value : '',
					'status' => $node->hasField('field_status') ? $node->get('field_status')->value : '',
					'assigned_to' => $this->userSummary($node->hasField('field_assigned_to') ? $node->get('field_assigned_to')->entity : NULL),
					'created_by' => $this->userSummary($node->getOwner()),
				];
			}

			$response_data = [
				'status' => 'Success',
				'result' => $tasks,
			];

			// Include a message if no tasks are assigned or found
			if (empty($tasks)) {
				$response_data['message'] = ($isSuperAdmin || $isAdminRole)
					? 'No tasks are currently assigned to any users.'
					: 'No tasks are currently assigned to you.';
			}

			return new JsonResponse($response_data, 200);
		} catch (\Exception $exception) {
			$this->logger->error($exception->getMessage());
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'An unexpected error occurred while fetching tasks.',
				'error' => $exception->getMessage(),
			], 500);
		}
	}

	/**
	 * Reduces a user entity to the small shape the SPA needs to render it.
	 *
	 * @param \Drupal\user\UserInterface|null $user
	 *   The user entity, or NULL if there's no owner/assignee loaded.
	 *
	 * @return array|null
	 */
	private function userSummary($user)
	{
		if (!$user) {
			return NULL;
		}
		$firstname = $user->hasField('field_firstname') ? trim((string) $user->get('field_firstname')->value) : '';
		$lastname  = $user->hasField('field_lastname')  ? trim((string) $user->get('field_lastname')->value)  : '';
		$fullname  = trim("$firstname $lastname") ?: $user->getDisplayName();
		return [
			'uid'      => (int) $user->id(),
			'name'     => $user->getDisplayName(),
			'fullname' => $fullname,
		];
	}

	public function post(Request $request)
	{
		if ($this->currentUser->isAnonymous()) {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Authentication required to create tasks.',
			], 403);
		}

		try {
			$content = $request->getContent();
			$data = json_decode($content, TRUE);

			$title = trim($data['title'] ?? '');
			$description = trim($data['description'] ?? '');
			$dueDate = trim($data['due_date'] ?? '');
			$severity = trim($data['severity'] ?? 'Low');
			$status = trim($data['status'] ?? 'Open');
			$assignedTo = isset($data['assigned_to']) ? (int) $data['assigned_to'] : 0;
			$project_id = isset($data['project_name']) ? (int) $data['project_name'] : '';

			if (empty($title) || empty($description) || empty($dueDate)) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Missing required fields: Title, Description, and Due Date are mandatory.',
				], 400);
			}

			if (empty($assignedTo)) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Missing required field: assigned_to (uid of the user to assign this task to).',
				], 400);
			}

			$assignedUser = $this->entityTypeManager->getStorage('user')->load($assignedTo);
			if (!$assignedUser || !$assignedUser->isActive()) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'The selected assignee is not a valid, active user.',
				], 400);
			}

			if ((int) $assignedUser->id() === 1) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Tasks cannot be assigned to the superadmin account.',
				], 400);
			}

			$node = Node::create([
				'type' => 'project_tracker',
				'title' => $title,
				'uid' => $this->currentUser->id(),
				'field_description' => $description,
				'field_due_date' => $dueDate,
				'field_severity' => $severity,
				'field_status' => $status,
				'field_assigned_to' => ['target_id' => $assignedTo],
				'field_project_id' => ['target_id' => $project_id],
				'status' => 1,
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
					'assigned_to' => $this->userSummary($assignedUser),
					'created_by' => $this->userSummary($this->entityTypeManager->getStorage('user')->load($this->currentUser->id())),
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
