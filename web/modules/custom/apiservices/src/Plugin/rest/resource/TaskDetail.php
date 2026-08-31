<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a REST resource for viewing/editing a single Project Tracker task.
 *
 * Split out from TaskList because TaskList's "canonical" path (/api/task-list)
 * is already a parameter-less collection route (list of "my" tasks) - adding
 * an {id}-based GET/POST to that same annotation key isn't possible, since
 * a single REST resource path can't serve both a fixed path and one with a
 * route parameter.
 *
 * @RestResource(
 *   id = "taskdetail_rest",
 *   label = @Translation("Task Detail API"),
 *   uri_paths = {
 *     "canonical" = "/api/task/{id}",
 *     "create" = "/api/task/{id}/update"
 *   }
 * )
 */
class TaskDetail extends ResourceBase
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
			$container->get('logger.factory')->get('task_detail_api'),
			$container->get('entity_type.manager'),
			$container->get('current_user')
		);
	}

	/**
	 * Loads a task, enforcing that it's a project_tracker node assigned to
	 * the current user. Returns the node, or an error JsonResponse to
	 * short-circuit with.
	 *
	 * @param int $id
	 *   The node id.
	 *
	 * @return \Drupal\node\NodeInterface|\Symfony\Component\HttpFoundation\JsonResponse
	 */
	private function loadOwnedTask($id)
	{
		if ($this->currentUser->isAnonymous()) {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Authentication required.',
			], 403);
		}
		$node = $this->entityTypeManager->getStorage('node')->load($id);

		if (!$node || $node->bundle() !== 'project_tracker') {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Task not found.',
			], 404);
		}
		return $node;
	}

	/**
	 * Reduces a user entity to the small shape the SPA needs to render it.
	 *
	 * @param \Drupal\user\UserInterface|null $user
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
			'uid' => (int) $user->id(),
			'name' => $user->getDisplayName(),
			'fullname' => $fullname,
		];
	}

	/**
	 * Responds to GET requests to fetch a single task.
	 * Route: GET /api/task/{id}?_format=json
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	public function get($id)
	{
		try {
			$node = $this->loadOwnedTask($id);
			if ($node instanceof JsonResponse) {
				return $node;
			}

			return new JsonResponse([
				'status' => 'Success',
				'result' => [
					'id' => $node->id(),
					'title' => $node->getTitle(),
					'description' => $node->hasField('field_description') ? $node->get('field_description')->value : '',
					'due_date' => $node->hasField('field_due_date') ? $node->get('field_due_date')->value : '',
					'severity' => $node->hasField('field_severity') ? $node->get('field_severity')->value : '',
					'status' => $node->hasField('field_status') ? $node->get('field_status')->value : '',
					'assigned_to' => $this->userSummary($node->hasField('field_assigned_to') ? $node->get('field_assigned_to')->entity : NULL),
					'created_by' => $this->userSummary($node->getOwner()),
				],
			], 200);
		} catch (\Exception $exception) {
			$this->logger->error($exception->getMessage());
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'An unexpected error occurred while fetching the task.',
				'error' => $exception->getMessage(),
			], 500);
		}
	}

	/**
	 * Responds to POST requests to update a task.
	 * Route: POST /api/task/{id}?_format=json
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	public function post($id, Request $request)
	{
		$node = $this->loadOwnedTask($id);
		if ($node instanceof JsonResponse) {
			return $node;
		}

		try {
			$data = json_decode($request->getContent(), TRUE) ?: [];

			$title = trim($data['title'] ?? $node->getTitle());
			$description = trim($data['description'] ?? '');
			$dueDate = trim($data['due_date'] ?? '');
			$severity = trim($data['severity'] ?? '');
			$status = trim($data['status'] ?? '');

			if (empty($title) || empty($description) || empty($dueDate)) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Missing required fields: Title, Description, and Due Date are mandatory.',
				], 400);
			}

			$node->setTitle($title);
			$node->set('field_description', $description);
			$node->set('field_due_date', $dueDate);
			if (!empty($severity)) {
				$node->set('field_severity', $severity);
			}
			if (!empty($status)) {
				$node->set('field_status', $status);
			}
			$node->save();

			return new JsonResponse([
				'status' => 'Success',
				'message' => 'Task updated successfully.',
				'result' => [
					'id' => $node->id(),
					'title' => $node->getTitle(),
					'description' => $node->get('field_description')->value,
					'due_date' => $node->get('field_due_date')->value,
					'severity' => $node->get('field_severity')->value,
					'status' => $node->get('field_status')->value,
					'assigned_to' => $this->userSummary($node->hasField('field_assigned_to') ? $node->get('field_assigned_to')->entity : NULL),
					'created_by' => $this->userSummary($node->getOwner()),
				],
			], 200);
		} catch (\Exception $exception) {
			$this->logger->error($exception->getMessage());
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Failed to update task.',
				'error' => $exception->getMessage(),
			], 500);
		}
	}
}
