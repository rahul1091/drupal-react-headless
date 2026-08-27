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
			$container->get('logger.factory')->get('client_list_api'),
			$container->get('entity_type.manager'),
			$container->get('current_user')
		);
	}

	/**
	 * Get All Clients List API
	 */
	public function get()
	{
		try {
			$is_anonymous = $this->currentUser->isAnonymous();
			$current_user_id = $this->currentUser->id();
			$current_user_name = $is_anonymous ? 'anonymous' : $this->currentUser->getAccountName();

			$account_fullname = '';
			$is_administrator = FALSE;

			if (!$is_anonymous) {
				$account = User::load($current_user_id);
				if ($account) {
					$account_fullname = trim(($account->get('field_firstname')->value ?? '') . ' ' . ($account->get('field_lastname')->value ?? ''));
					$is_administrator = $account->hasRole('administrator');
				}
			}

			// Load project details query
			$query = \Drupal::entityQuery('node')
				->accessCheck(TRUE)
				->condition('type', 'project_details')
				->condition('status', 1)
				->sort('created', 'DESC');

			// If anonymous or non-administrator, handle restrictions.
			if ($is_anonymous) {
				// Anonymous users can view published projects (accessCheck handles node permissions)
			} elseif (!$is_administrator) {
				$query->condition('field_project_manager.target_id', $current_user_id);
			}

			$nids = $query->execute();
			$nodes = Node::loadMultiple($nids);
			$clients = [];

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

				$clients[] = [
					'nid' => $node->id(),
					'project_id' => $node->id(),
					'project_name' => $node->getTitle(),
					'client_name' => $node->get('field_client_name')->value,
					'client_city' => $node->get('field_client_city')->value,
					'client_country' => $node->get('field_client_country')->value,
					'current_user_id' => $current_user_id,
					'current_user_name' => $current_user_name,
					'current_user_fullname' => $account_fullname,
					'project_manager' => $project_manager,
				];
			}

			$message = 'Client Project List';
			if ($is_anonymous) {
				$message = 'Public Client Project List';
			} elseif ($is_administrator) {
				$message = 'All Client Project List';
			} else {
				$message = 'Assigned Client Project List';
			}

			return new JsonResponse([
				'status' => 'Success',
				'message' => $message,
				'result' => $clients === [] ? 'No Client Project Found.' : $clients,
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
