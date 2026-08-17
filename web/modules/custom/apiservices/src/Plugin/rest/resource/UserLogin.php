<?php

namespace Drupal\apiservices\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a REST resource for custom user authentication.
 *
 * @RestResource(
 *   id = "user_login_rest",
 *   label = @Translation("User Login API"),
 *   uri_paths = {
 *     "create" = "/api/user-login",
 *   }
 * )
 */
class UserLogin extends ResourceBase
{

  /**
   * The user storage service.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The user authentication service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Constructs a new UserLogin object.
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
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication service.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider service.
   */
  public function __construct(
    array $config,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    UserStorageInterface $user_storage,
    CsrfTokenGenerator $csrf_token,
    UserAuthInterface $user_auth,
    RouteProviderInterface $route_provider
  ) {
    parent::__construct($config, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->userStorage = $user_storage;
    $this->csrfToken = $csrf_token;
    $this->userAuth = $user_auth;
    $this->routeProvider = $route_provider;
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
      $container->get('logger.factory')->get('user_login_api'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('csrf_token'),
      $container->get('user.auth'),
      $container->get('router.route_provider')
    );
  }

  /**
   * Authenticates and logs in a user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The API response containing user details and tokens.
   */
  public function post(Request $request)
  {
    try {
      $content = $request->getContent();
      $params = json_decode($content, TRUE);

      $email = trim($params['email'] ?? '');
      $password = $params['password'] ?? '';

      if (empty($email) || empty($password)) {
        return new JsonResponse([
          'status' => 'Error',
          'message' => 'Missing / Invalid Credentials',
        ], 400);
      }

      // Load user object by mail using injected entity storage
      $users = $this->userStorage->loadByProperties(['mail' => $email]);
      /** @var \Drupal\user\UserInterface|null $user */
      $user = reset($users);

      if (!$user) {
        return new JsonResponse([
          'status' => 'Error',
          'message' => 'User Not Found',
          'result' => 'No user registered with ' . $email,
        ], 404);
      }

      $username = $user->getAccountName();

      if ($uid = $this->userAuth->authenticate($username, $password)) {
        if (!$user->isActive()) {
          return new JsonResponse([
            'status' => 'Error',
            'message' => 'Login Failed',
            'result' => 'Your account is disabled. Please contact the administrator.',
          ], 403);
        }

        // Finalize login session
        $this->userLoginFinalize($user);

        $roles = $user->getRoles();
        $isAdmin = in_array('administrator', $roles, TRUE);
        // Remove "authenticated".
        $roles = array_diff($roles, ['authenticated']);

        // Convert array to string.
        $user_role = implode(', ', $roles);

        // Build user payload
        $responseData = [
          'current_user' => [
            'uid' => $user->id(),
            'username' => $user->getAccountName(),
            'email' => $user->getEmail(),
            'firstname' => $user->hasField('field_firstname') ? $user->get('field_firstname')->value : '',
            'lastname' => $user->hasField('field_lastname') ? $user->get('field_lastname')->value : '',
            'role' => ucfirst($user_role),
            'isAdmin' => $isAdmin,
            'created' => date('d-m-Y', $user->getCreatedTime()),
          ],
          'csrf_token' => $this->csrfToken->get('rest'),
        ];

        // Get logout token
        $logoutRoute = $this->routeProvider->getRouteByName('user.logout.http');
        $logoutPath = ltrim($logoutRoute->getPath(), '/');
        $responseData['logout_token'] = $this->csrfToken->get($logoutPath);

        return new JsonResponse([
          'status' => 'Success',
          'message' => 'Login Success',
          'result' => $responseData,
        ], 200);
      }

      return new JsonResponse([
        'status' => 'Error',
        'message' => 'Missing / Invalid Credentials',
      ], 401);
    } catch (\Exception $exception) {
      return $this->handleException($exception);
    }
  }

  /**
   * Finalizes the user login.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   */
  protected function userLoginFinalize(UserInterface $user)
  {
    user_login_finalize($user);
  }

  /**
   * Logs exception details and outputs a JSON error.
   *
   * @param \Exception $exception
   *   The caught exception.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON error response.
   */
  private function handleException(\Exception $exception)
  {
    $this->logger->error($exception->getMessage());
    return new JsonResponse([
      'status' => 'Error',
      'message' => 'An unexpected error occurred.',
      'error' => $exception->getMessage(),
    ], 500);
  }
}
