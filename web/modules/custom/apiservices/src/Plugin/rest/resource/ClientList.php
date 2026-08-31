<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides Client List API.
 *
 * @RestResource(
 *   id = "client_list_rest",
 *   label = @Translation("Client List API"),
 *   uri_paths = {
 *     "canonical" = "/api/client-list"
 *   }
 * )
 */
class ClientList extends ResourceBase
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
	 * Constructs a new ClientList instance.
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
			$container->get('logger.factory')->get('client_list_api'),
			$container->get('entity_type.manager'),
			$container->get('current_user')
		);
	}

	/**
	 * Responds to GET requests to fetch Client Project List.
	 * Route: GET /api/client-list?_format=json
	 *
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	public function get(): JsonResponse
	{
		try {
			$currentUid = (int) $this->currentUser->id();
			$isAnonymous = $this->currentUser->isAnonymous();
			$currentUserName = $isAnonymous ? 'anonymous' : $this->currentUser->getAccountName();

			$accountFullname = '';
			$nodeStorage = $this->entityTypeManager->getStorage('node');
			$userStorage = $this->entityTypeManager->getStorage('user');

			if (!$isAnonymous) {
				$account = $userStorage->load($currentUid);
				if ($account) {
					$firstName = $account->hasField('field_firstname') ? trim((string) $account->get('field_firstname')->value) : '';
					$lastName  = $account->hasField('field_lastname') ? trim((string) $account->get('field_lastname')->value) : '';
					$accountFullname = trim("$firstName $lastName") ?: $account->getDisplayName();
				}
			}

			$roles = $this->currentUser->getRoles();
			$isSuperAdmin = ($currentUid === 1);
			$isAdmin = in_array('administrator', $roles, TRUE);
			$isClient = in_array('client', $roles, TRUE);
			$isManager = in_array('manager', $roles, TRUE);
			$isEngineer = in_array('engineer', $roles, TRUE);

			// Base query for project_details nodes
			$query = $nodeStorage->getQuery()
				->condition('type', 'project_details')
				->condition('status', 1)
				->sort('created', 'DESC')
				->accessCheck(FALSE);

			// Role-based scoping logic
			if ($isAnonymous) {
				$message = 'Public Client Project List';
			} elseif ($isSuperAdmin || $isAdmin) {
				// Admins can view all projects (no additional filters)
				$message = 'All Client Project List';
			} elseif ($isClient) {
				// Clients view projects where they are assigned as client manager
				$query->condition('field_client_manager', $currentUid);
				$message = 'Assigned Client Project List';
			} elseif ($isManager) {
				// Managers view projects where they are assigned as project manager
				$query->condition('field_project_manager', $currentUid);
				$message = 'Assigned Client Project List';
			} elseif ($isEngineer) {
				// Engineers view projects that contain tasks assigned to them
				$assignedTaskProjectNids = $nodeStorage->getQuery()
					->condition('type', 'project_tracker')
					->condition('field_assigned_to', $currentUid)
					->condition('status', 1)
					->accessCheck(FALSE)
					->execute();

				if ($assignedTaskProjectNids) {
					$taskNodes = $nodeStorage->loadMultiple($assignedTaskProjectNids);
					$projectIds = array_filter(array_map(function ($task) {
						return $task->get('field_project_id')->target_id;
					}, $taskNodes));

					if (!empty($projectIds)) {
						$query->condition('nid', array_unique($projectIds), 'IN');
					} else {
						// Engineer has no valid assigned project tasks
						return new JsonResponse([
							'status' => 'Success',
							'message' => 'Assigned Client Project List',
							'result' => 'No Client Project Found.',
						]);
					}
				} else {
					// Engineer has no tasks assigned
					return new JsonResponse([
						'status' => 'Success',
						'message' => 'Assigned Client Project List',
						'result' => 'No Client Project Found.',
					]);
				}
				$message = 'Assigned Client Project List';
			} else {
				$message = 'Client Project List';
			}

			$nids = $query->execute();
			$clients = [];

			if (!empty($nids)) {
				$nodes = $nodeStorage->loadMultiple($nids);

				foreach ($nodes as $node) {
					$projectManager = [];
					if (!$node->get('field_project_manager')->isEmpty()) {
						$pmUser = $node->get('field_project_manager')->entity;
						if ($pmUser) {
							$pmFirst = $pmUser->hasField('field_firstname') ? trim((string) $pmUser->get('field_firstname')->value) : '';
							$pmLast  = $pmUser->hasField('field_lastname') ? trim((string) $pmUser->get('field_lastname')->value) : '';
							$projectManager = [
								'uid' => (int) $pmUser->id(),
								'username' => $pmUser->getAccountName(),
								'fullname' => trim("$pmFirst $pmLast") ?: $pmUser->getDisplayName(),
							];
						}
					}

					$clients[] = [
						'nid' => (int) $node->id(),
						'project_id' => (int) $node->id(),
						'project_name' => $node->getTitle(),
						'client_name' => $node->get('field_client_name')->value ?? '',
						'client_city' => $node->get('field_client_city')->value ?? '',
						'client_country' => $node->get('field_client_country')->value ?? '',
						'current_user_id' => $currentUid,
						'current_user_name' => $currentUserName,
						'current_user_fullname' => $accountFullname,
						'project_manager' => $projectManager,
					];
				}
			}

			return new JsonResponse([
				'status' => 'Success',
				'message' => $message,
				'result' => empty($clients) ? 'No Client Project Found.' : $clients,
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
