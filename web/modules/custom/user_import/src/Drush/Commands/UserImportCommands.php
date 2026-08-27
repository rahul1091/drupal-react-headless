<?php

declare(strict_types=1);

namespace Drupal\user_import\Drush\Commands;

use Drupal\user_import\UserCreator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface;

/**
 * Provides commands for creating users.
 */
final class UserImportCommands extends DrushCommands {

  /**
   * Constructs the user import command.
   */
  public function __construct(
    private readonly UserCreator $userCreator,
  ) {
    parent::__construct();
  }

  /**
   * Creates the command class with its dependencies.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('user_import.creator'),
    );
  }

  /**
   * Creates predefined client and engineer users.
   */
  #[CLI\Command(
    name: 'user-import:create-users',
    aliases: ['uic'],
  )]
  #[CLI\Usage(
    name: 'drush user-import:create-users',
    description: 'Creates predefined client and engineer users.',
  )]
  public function createUsers(): void {
		$users = [
      [
        'first_name' => 'Peter',
        'last_name' => 'Parker',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Bruce',
        'last_name' => 'Wayne',
        'role' => 'client',
      ],
      [
        'first_name' => 'Diana',
        'last_name' => 'Prince',
        'role' => 'client',
      ],
      [
        'first_name' => 'Steve',
        'last_name' => 'Rogers',
        'role' => 'client',
      ],
      [
        'first_name' => 'Natasha',
        'last_name' => 'Romanoff',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Barry',
        'last_name' => 'Allen',
        'role' => 'client',
      ],
      [
        'first_name' => 'Arthur',
        'last_name' => 'Curry',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Hal',
        'last_name' => 'Jordan',
        'role' => 'client',
      ],
      [
        'first_name' => 'Wanda',
        'last_name' => 'Maximoff',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Stephen',
        'last_name' => 'Strange',
        'role' => 'client',
      ],
      [
        'first_name' => 'Carol',
        'last_name' => 'Danvers',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Victor',
        'last_name' => 'Stone',
        'role' => 'client',
      ],
      [
        'first_name' => 'Logan',
        'last_name' => 'Howlett',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Matt',
        'last_name' => 'Murdock',
        'role' => 'client',
      ],
      [
        'first_name' => 'Jessica',
        'last_name' => 'Jones',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Arthur',
        'last_name' => 'Pendragon',
        'role' => 'client',
      ],
      [
        'first_name' => 'Billy',
        'last_name' => 'Batson',
        'role' => 'engineer',
      ],
      [
        'first_name' => 'Jean',
        'last_name' => 'Grey',
        'role' => 'client',
      ],
    ];

    $created = 0;
    $failed = 0;

    foreach ($users as $userData) {
      try {
        $user = $this->userCreator->createUser(
          $userData['first_name'],
          $userData['last_name'],
          $userData['role'],
        );

        $this->io()->writeln(
          sprintf(
            '<info>Created:</info> %s (%s)',
            $user->getAccountName(),
            $user->getEmail(),
          ),
        );

        $created++;
      }
      catch (\Throwable $exception) {
        $this->io()->error(
          sprintf(
            'Failed to create %s %s: %s',
            $userData['first_name'],
            $userData['last_name'],
            $exception->getMessage(),
          ),
        );

        $failed++;
      }
    }

    if ($failed === 0) {
      $this->io()->success(
        sprintf('%d users created successfully.', $created),
      );
    }
    else {
      $this->io()->warning(
        sprintf(
          'Completed with %d successful and %d failed.',
          $created,
          $failed,
        ),
      );
    }
  }

}
