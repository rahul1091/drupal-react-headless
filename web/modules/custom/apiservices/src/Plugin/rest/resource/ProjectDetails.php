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

/**
 * Provides Project Details API.
 *
 * @RestResource(
 *   id = "project_details_rest",
 *   label = @Translation("Project Details API"),
 *   uri_paths = {
 *     "canonical" = "/api/project-details",
 *     "create" = "/api/add-project"
 *   }
 * )
 */
class ProjectDetails extends ResourceBase
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
			$container->get('logger.factory')->get('project_details_api'),
			$container->get('entity_type.manager'),
			$container->get('current_user')
		);
	}

	/**
	 * Get All Projects List API
	 */
	public function get()
	{
		if ($this->currentUser->isAnonymous()) {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Authenticated user access required to see the project details.',
			], 403);
		}

		try {
			$query = \Drupal::entityQuery('node')
				->accessCheck(TRUE)
				->condition('type', 'project_details')
				->condition('status', 1)
				->sort('created', 'DESC');

			$nids = $query->execute();
			$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
			$projects = [];

			foreach ($nodes as $node) {
				$project_manager = [];
				if (!$node->get('field_project_manager')->isEmpty()) {
					$user = $node->get('field_project_manager')->entity;
					if ($user) {
						$project_manager = [
							'uid' => $user->id(),
							'name' => $user->getDisplayName(),
							'mail' => $user->getEmail(),
							'fullname' => trim($user->get('field_firstname')->value . ' ' . $user->get('field_lastname')->value),
						];
					}
				}

				$project_details = [
					'project_id' => $node->id(),
					'title' => $node->getTitle(),
					'project_code' => $node->get('field_project_code')->value,
					'description' => $node->get('field_description')->value,
					'start_date' => date("d-m-Y", strtotime($node->get('field_start_date')->value)),
					'end_date' => date("d-m-Y", strtotime($node->get('field_end_date')->value)),
					'project_manager' => $project_manager,
				];

				$client_details = [
					'client_name' => $node->get('field_client_name')->value,
					'client_address' => $node->get('field_client_address')->value,
					'client_city' => $node->get('field_client_city')->value,
					'client_country' => $node->get('field_client_country')->value,
					'client_budget' => $node->get('field_client_budget')->value,
				];

				$projects[] = [
					'nid' => $node->id(),
					'created' => date('d-m-Y', $node->getCreatedTime()),
					'project_details' => $project_details,
					'client_details' => $client_details,
					'project_issues' => []
				];
			}

			return new JsonResponse([
				'status' => 'Success',
				'message' => 'Project List',
				'result' => $projects === [] ? 'No Projects Found.' : $projects,
			]);
		} catch (\Exception $exception) {
			return $this->exception_error_msg($exception->getMessage());
		}
	}

	/**
	 * Reduces a user entity to the small shape the SPA needs to render it.
	 * @param \Drupal\user\UserInterface|null $user
	 *   The user entity, or NULL if there's no owner/assignee loaded.
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

	/**
	 * Create a new Project API
	 */
	public function post(Request $request)
	{
		if ($this->currentUser->isAnonymous() || !in_array('administrator', $this->currentUser->getRoles(), TRUE)) {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Administrator access required to create new project details.',
			], 403);
		}

		try {
			$content = $request->getContent();
			$data = json_decode($content, TRUE);

			$project_name = trim($data['project_name'] ?? '');
			$project_code = trim($data['project_code'] ?? '');
			$project_manager = isset($data['project_manager']) ? (int) $data['project_manager'] : 0;
			$description = trim($data['description'] ?? '');
			$start_date = trim($data['start_date'] ?? '');
			$end_date = trim($data['end_date'] ?? '');
			$client_name = trim($data['client_name'] ?? '');
			$client_manager = isset($data['client_manager']) ? (int) $data['client_manager'] : 0;
			$client_address = trim($data['client_address'] ?? '');
			$client_city = trim($data['client_city'] ?? '');
			$client_country = trim($data['client_country'] ?? '');
			$client_budget = trim($data['client_budget'] ?? '');

			// Validation check for required fields
			if (empty($project_name) || empty($project_code) || empty($description) || empty($start_date) || empty($end_date) || empty($client_name) || empty($client_address) || empty($client_city) || empty($client_country) || empty($client_budget)) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Missing required fields.',
				], 400);
			}

			if (empty($project_manager) || empty($client_manager)) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Missing required field: Project/Client Manager (uid of the user to assign this project to).',
				], 400);
			}

			$project_manager = $this->entityTypeManager->getStorage('user')->load($project_manager);
			if (!$project_manager || !$project_manager->isActive()) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'The selected assignee is not a valid, active user.',
				], 400);
			}
			$client_manager = $this->entityTypeManager->getStorage('user')->load($client_manager);
			if (!$client_manager || !$client_manager->isActive()) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'The selected assignee is not a valid, active user.',
				], 400);
			}
			// Superadmin (uid=1) is the oversight role and should not be assigned tasks — the user list the frontend shows already excludes them
			// This check prevents a crafted POST from bypassing that restriction.
			if ((int) $project_manager->id() === 1 || (int) $client_manager->id() === 1) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Project Manager cannot be assigned to the superadmin account.',
				], 400);
			}

			// Create new Project Tracker node programmatically
			$node = Node::create([
				'type' => 'project_details',
				'title' => $project_name,
				'uid' => $this->currentUser->id(),
				'field_project_code' => $project_code,
				'field_project_manager' => ['target_id' => $project_manager->id()],
				'field_description' => $description,
				'field_start_date' => $start_date,
				'field_end_date' => $end_date,
				'field_client_name' => $client_name,
				'field_client_manager' => ['target_id' => $client_manager->id()],
				'field_client_address' => $client_address,
				'field_client_city' => $client_city,
				'field_client_country' => $client_country,
				'field_client_budget' => $client_budget,
				'status' => 1, // Published
			]);

			$node->save();

			return new JsonResponse([
				'status' => 'Success',
				'message' => 'Project created successfully.',
				'result' => [
					'id' => $node->id(),
					'title' => $node->getTitle(),
					'field_project_code' => $project_code,
					'field_project_manager' => $this->userSummary($project_manager),
					'field_description' => $description,
					'field_start_date' => $start_date,
					'field_end_date' => $end_date,
					'field_client_name' => $client_name,
					'field_client_manager' => $this->userSummary($client_manager),
					'field_client_address' => $client_address,
					'field_client_city' => $client_city,
					'field_client_country' => $client_country,
					'field_client_budget' => $client_budget,
					'created_by' => $this->userSummary($this->entityTypeManager->getStorage('user')->load($this->currentUser->id())),
				],
			], 201);
		} catch (\Exception $exception) {
			$this->logger->error($exception->getMessage());
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Failed to create project.',
				'error' => $exception->getMessage(),
			], 500);
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
