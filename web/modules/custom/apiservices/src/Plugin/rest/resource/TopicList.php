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
use Drupal\file\Entity\File;

/**
 * Provides a resource to get view modes by entity and bundle.
 * @RestResource(
 *   id = "topiclist_rest",
 *   label = @Translation("Topiclist API"),
 *   uri_paths = {
 *     "canonical" = "/api/topiclist",
 *     "create" = "/api/add-topic"
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
	public function get(Request $request)
	{
		try {
			$langcode = $request?->query->get('langcode', \Drupal::languageManager()
				->getCurrentLanguage()
				->getId());

			$project_nids = \Drupal::entityQuery('node')
				->accessCheck(TRUE)
				->condition('type', 'topic_list')
				->condition('status', 1)
				->execute();

			$project_nodes = Node::loadMultiple($project_nids);

			$project_list_data = [];

			foreach ($project_nodes as $node) {
				// Load translated version if available.
				if ($node->hasTranslation($langcode)) {
					$node = $node->getTranslation($langcode);
				}

				$topic_fid = $node->get('field_content_image')->target_id;
        $topic_image = File::load($topic_fid);

				$project_list_data[] = [
					'id' => $node->id(),
					'title' => $node->getTitle(),
					'subheading' => $node->get('field_sub_heading')->value ?? '',
					'description' => $node->get('field_description')->value ?? '',
					'trending' => $node->get('field_trending')->value ?? '',
					'topic_img' => \Drupal::service('file_url_generator')->generateAbsoluteString($topic_image->getFileUri())
				];
			}

			return new JsonResponse([
				'status' => 'Success',
				'message' => 'Topic List',
				'language' => $langcode,
				'result' => $project_list_data,
			]);
		} catch (\Exception $exception) {
			return $this->exception_error_msg($exception->getMessage());
		}
	}

	/**
	 * Creates a new topic_list "topic" node. Admin-only: this isn't
	 * content any assigned user should be able to publish, unlike task
	 * creation (which any authenticated user can do for themselves).
	 * Route: POST /api/add-topic?_format=json
	 */
	public function post(Request $request)
	{
		if ($this->loggedUser->isAnonymous() || !in_array('administrator', $this->loggedUser->getRoles(), TRUE)) {
			return new JsonResponse([
				'status' => 'Error',
				'message' => 'Administrator access required to create topics.',
			], 403);
		}

		try {
			$data = json_decode($request->getContent(), TRUE) ?: [];

			$title = trim($data['title'] ?? '');
			$subheading = trim($data['subheading'] ?? '');
			$description = trim($data['description'] ?? '');
			$trending = strtolower(trim((string) ($data['trending'] ?? 'no'))) === 'yes' ? 'yes' : 'no';

			if (empty($title) || empty($description)) {
				return new JsonResponse([
					'status' => 'Error',
					'message' => 'Missing required fields: Title and Description are mandatory.',
				], 400);
			}

			$node = Node::create([
				'type' => 'topic_list',
				'title' => $title,
				'field_sub_heading' => $subheading,
				'field_description' => $description,
				'field_trending' => $trending,
				'status' => 1,
			]);

			$node->save();

			return new JsonResponse([
				'status' => 'Success',
				'message' => 'Topic created successfully.',
				'result' => [
					'id' => $node->id(),
					'title' => $node->getTitle(),
					'subheading' => $subheading,
					'description' => $description,
					'trending' => $trending,
				],
			], 201);
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
