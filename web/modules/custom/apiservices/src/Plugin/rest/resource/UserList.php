<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a REST resource listing active users, for task assignment.
 *
 * @RestResource(
 *   id = "userlist_rest",
 *   label = @Translation("User List API"),
 *   uri_paths = {
 *     "canonical" = "/api/user-list"
 *   }
 * )
 */
class UserList extends ResourceBase
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
			$container->get('logger.factory')->get('user_list_api'),
			$container->get('entity_type.manager'),
			$container->get('current_user')
		);
	}

	/**
	 * Responds to GET requests to fetch the assignable user list.
	 * Route: GET /api/user-list?_format=json
	 *
	 * Requires authentication - this is a directory of user accounts
	 * (name/uid), not something anonymous visitors should be able to list.
	 *
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 *   JSON list of active users, sorted by name.
	 */
	public function get()
	{
		if ($this->currentUser->isAnonymous()) {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Authentication required to view the user list.',
			], 403);
		}

		try {
			$userStorage = $this->entityTypeManager->getStorage('user');
			// Return all active users except uid=1
			$uids = $userStorage->getQuery()
				->condition('status', 1)
				->condition('uid', 1, '>')
				->accessCheck(FALSE)
				->execute();

			/** @var \Drupal\user\UserInterface[] $users */
			$users = $userStorage->loadMultiple($uids);
			$result = [];

			foreach ($users as $user) {
				$firstname = $user->hasField('field_firstname') ? trim((string) $user->get('field_firstname')->value) : '';
				$lastname  = $user->hasField('field_lastname')  ? trim((string) $user->get('field_lastname')->value)  : '';
				$fullname  = trim("$firstname $lastname") ?: $user->getDisplayName();

				// Fetch and format user roles (excluding 'authenticated')
				$roles = $user->getRoles();
				$roles = array_diff($roles, ['authenticated']);
				$user_role = !empty($roles) ? ucfirst(implode(', ', $roles)) : 'User';

				$result[] = [
					'uid' => (int) $user->id(),
					'name' => $user->getDisplayName(),
					'fullname' => $fullname,
					'email' => $user->getEmail(),
					'role' => $user_role,
				];
			}

			usort($result, fn($a, $b) => strcasecmp($a['fullname'], $b['fullname']));

			return new JsonResponse([
				'status' => 'Success',
				'result' => $result,
			], 200);
		} catch (\Exception $exception) {
			$this->logger->error($exception->getMessage());
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'An unexpected error occurred while fetching users.',
				'error' => $exception->getMessage(),
			], 500);
		}
	}
}
