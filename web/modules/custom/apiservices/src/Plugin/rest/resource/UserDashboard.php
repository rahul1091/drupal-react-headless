<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get user dashboard details.
 *
 * @RestResource(
 *   id = "user_dashboard_rest",
 *   label = @Translation("User Dashboard API"),
 *   uri_paths = {
 *     "canonical" = "/api/user-dashboard"
 *   }
 * )
 */
class UserDashboard extends ResourceBase
{

	/**
	 * The current account.
	 *
	 * @var \Drupal\Core\Session\AccountProxyInterface
	 */
	protected $currentUser;

	/**
	 * The entity type manager service.
	 *
	 * @var \Drupal\Core\Entity\EntityTypeManagerInterface
	 */
	protected $entityTypeManager;

	/**
	 * Constructs a UserDashboard object.
	 */
	public function __construct(
		array $config,
		$plugin_id,
		$plugin_definition,
		array $serializer_formats,
		LoggerInterface $logger,
		AccountProxyInterface $current_user,
		EntityTypeManagerInterface $entity_type_manager
	) {
		parent::__construct($config, $plugin_id, $plugin_definition, $serializer_formats, $logger);
		$this->currentUser = $current_user;
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
			$container->get('logger.factory')->get('user_dashboard_api'),
			$container->get('current_user'),
			$container->get('entity_type.manager')
		);
	}

	/**
	 * Responds to GET requests.
	 *
	 * @return \Drupal\rest\ResourceResponse
	 *   The HTTP response object.
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
	 */
	public function get()
	{
		if ($this->currentUser->isAnonymous()) {
			throw new AccessDeniedHttpException('Authenticated user access required to see the dashboard details.');
		}

		$account_storage = $this->entityTypeManager->getStorage('user');
		/** @var \Drupal\user\UserInterface $account */
		$account = $account_storage->load($this->currentUser->id());

		$user_roles = $account->getRoles();
		$roles_cleaned = array_values(array_diff($user_roles, ['authenticated']));

		$project_data = [];
		$node_storage = $this->entityTypeManager->getStorage('node');

		// -------------------------------------------------------------------
		// 1. MANAGER ROLE
		// -------------------------------------------------------------------
		if (in_array('manager', $roles_cleaned, TRUE)) {
			$project_nids = $node_storage->getQuery()
				->accessCheck(TRUE)
				->condition('type', 'project_details')
				->condition('status', 1)
				->condition('field_project_manager.target_id', $account->id())
				->sort('created', 'DESC')
				->execute();

			if (!empty($project_nids)) {
				/** @var \Drupal\node\NodeInterface[] $projects */
				$projects = $node_storage->loadMultiple($project_nids);

				foreach ($projects as $project) {
					$client_info = $this->getUserInfo($project, 'field_client_manager');
					// Fetch people working on tasks in this project
					$task_assignees = $this->getTaskAssigneesByProject($project->id());

					$start_date = $project->get('field_start_date')->value;
					$end_date = $project->get('field_end_date')->value;

					$project_data[] = [
						'project_id' => (int) $project->id(),
						'project_name' => $project->label(),
						'project_code' => $project->get('field_project_code')->value,
						'client_poc' => $client_info['name'],
						'client_poc_email' => $client_info['email'],
						'client_name' => $project->get('field_client_name')->value,
						'client_city' => $project->get('field_client_city')->value,
						'client_country' => $project->get('field_client_country')->value,
						'start_date' => $start_date ? date('d-m-Y', strtotime($start_date)) : '',
						'end_date' => $end_date ? date('d-m-Y', strtotime($end_date)) : '',
						'task_assignees' => $task_assignees,
					];
				}
			}
		}
		// -------------------------------------------------------------------
		// 2. ENGINEER ROLE
		// -------------------------------------------------------------------
		elseif (in_array('engineer', $roles_cleaned, TRUE)) {
			// Find tasks assigned to this engineer
			$task_nids = $node_storage->getQuery()
				->accessCheck(TRUE)
				->condition('type', 'project_tracker')
				->condition('status', 1)
				->condition('field_assigned_to.target_id', $account->id())
				->execute();

			if (!empty($task_nids)) {
				/** @var \Drupal\node\NodeInterface[] $tasks */
				$tasks = $node_storage->loadMultiple($task_nids);
				$project_nids = [];

				foreach ($tasks as $task) {
					if (!$task->get('field_project_id')->isEmpty()) {
						$project_nids[] = $task->get('field_project_id')->target_id;
					}
				}

				$project_nids = array_unique(array_filter($project_nids));

				if (!empty($project_nids)) {
					/** @var \Drupal\node\NodeInterface[] $projects */
					$projects = $node_storage->loadMultiple($project_nids);

					foreach ($projects as $project) {
						$manager_info = $this->getUserInfo($project, 'field_project_manager');

						$start_date = $project->get('field_start_date')->value;
						$end_date = $project->get('field_end_date')->value;

						$project_data[] = [
							'project_id' => (int) $project->id(),
							'project_name' => $project->label(),
							'project_code' => $project->get('field_project_code')->value,
							'manager_name' => $manager_info['name'],
							'manager_email' => $manager_info['email'],
							'client_name' => $project->get('field_client_name')->value,
							'client_city' => $project->get('field_client_city')->value,
							'client_country' => $project->get('field_client_country')->value,
							'start_date' => $start_date ? date('d-m-Y', strtotime($start_date)) : '',
							'end_date' => $end_date ? date('d-m-Y', strtotime($end_date)) : '',
						];
					}
				}
			}
		}
		// -------------------------------------------------------------------
		// 3. CLIENT ROLE
		// -------------------------------------------------------------------
		elseif (in_array('client', $roles_cleaned, TRUE)) {
			$project_nids = $node_storage->getQuery()
				->accessCheck(TRUE)
				->condition('type', 'project_details')
				->condition('status', 1)
				->condition('field_client_manager.target_id', $account->id())
				->sort('created', 'DESC')
				->execute();

			if (!empty($project_nids)) {
				/** @var \Drupal\node\NodeInterface[] $projects */
				$projects = $node_storage->loadMultiple($project_nids);

				foreach ($projects as $project) {
					$poc_info = $this->getUserInfo($project, 'field_project_manager');

					$start_date = $project->get('field_start_date')->value;
					$end_date = $project->get('field_end_date')->value;

					$project_data[] = [
						'project_id' => (int) $project->id(),
						'project_name' => $project->label(),
						'project_code' => $project->get('field_project_code')->value,
						'project_poc' => $poc_info['name'],
						'project_poc_email' => $poc_info['email'],
						'client_name' => $project->get('field_client_name')->value,
						'client_city' => $project->get('field_client_city')->value,
						'client_country' => $project->get('field_client_country')->value,
						'start_date' => $start_date ? date('d-m-Y', strtotime($start_date)) : '',
						'end_date' => $end_date ? date('d-m-Y', strtotime($end_date)) : '',
					];
				}
			}
		}

		$response_data = [
			'status' => 'Success',
			'message' => 'Dashboard Data',
			'result' => [
				'user_data' => [
					'role' => implode(', ', $roles_cleaned),
				],
				'project_data' => $project_data,
			],
		];

		// Return Cacheable ResourceResponse.
		$response = new ResourceResponse($response_data, 200);
		$cache_metadata = new CacheableMetadata();
		$cache_metadata->addCacheContexts(['user']);
		$cache_metadata->addCacheTags([
			'node_list:project_details',
			'node_list:project_tracker',
			'user:' . $account->id(),
		]);
		$response->addCacheableDependency($cache_metadata);

		return $response;
	}

	/**
	 * Helper to collect all users assigned to tasks under a specific project ID.
	 */
	private function getTaskAssigneesByProject($project_id)
	{
		$node_storage = $this->entityTypeManager->getStorage('node');

		$task_nids = $node_storage->getQuery()
			->accessCheck(TRUE)
			->condition('type', 'project_tracker')
			->condition('status', 1)
			->condition('field_project_id.target_id', $project_id)
			->execute();

		if (empty($task_nids)) {
			return [];
		}

		/** @var \Drupal\node\NodeInterface[] $tasks */
		$tasks = $node_storage->loadMultiple($task_nids);
		$assignees = [];

		foreach ($tasks as $task) {
			if (!$task->get('field_assigned_to')->isEmpty()) {
				/** @var \Drupal\user\UserInterface $user */
				$user = $task->get('field_assigned_to')->entity;
				if ($user) {
					$uid = (int) $user->id();
					if (!isset($assignees[$uid])) {
						$fname = $user->hasField('field_firstname') ? $user->get('field_firstname')->value : '';
						$lname = $user->hasField('field_lastname') ? $user->get('field_lastname')->value : '';
						$fullname = trim("{$fname} {$lname}") ?: $user->getAccountName();

						$assignees[$uid] = [
							'user_id' => $uid,
							'name' => $fullname,
							'email' => $user->getEmail(),
						];
					}
				}
			}
		}
		return array_values($assignees);
	}

	/**
	 * Helper to fetch user details from an entity reference field on a node.
	 */
	private function getUserInfo($node, $field_name)
	{
		$info = ['name' => '', 'email' => ''];

		if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
			/** @var \Drupal\user\UserInterface $user */
			$user = $node->get($field_name)->entity;
			if ($user) {
				$fname = $user->hasField('field_firstname') ? $user->get('field_firstname')->value : '';
				$lname = $user->hasField('field_lastname') ? $user->get('field_lastname')->value : '';
				$info['name'] = trim("{$fname} {$lname}") ?: $user->getAccountName();
				$info['email'] = $user->getEmail();
			}
		}

		return $info;
	}
}
