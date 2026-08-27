<?php

declare(strict_types=1);

namespace Drupal\user_import;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Creates client and engineer user accounts.
 */
final class UserCreator
{
	/**
	 * Constructs the user creator service.
	 */
	public function __construct(
		private readonly EntityTypeManagerInterface $entityTypeManager,
	) {}

	/**
	 * Creates a user account.
	 *
	 * @throws \InvalidArgumentException
	 *   Thrown when an unsupported role is provided.
	 * @throws \RuntimeException
	 *   Thrown when a required role or user field does not exist.
	 */
	public function createUser(
		string $firstName,
		string $lastName,
		string $role,
	): UserInterface {
		$firstName = trim($firstName);
		$lastName = trim($lastName);
		$role = strtolower(trim($role));

		if ($firstName === '' || $lastName === '') {
			throw new \InvalidArgumentException(
				'First name and last name are required.',
			);
		}

		$allowedRoles = ['client', 'engineer'];

		if (!in_array($role, $allowedRoles, TRUE)) {
			throw new \InvalidArgumentException(
				sprintf(
					'Invalid role "%s". Allowed roles are: %s.',
					$role,
					implode(', ', $allowedRoles),
				),
			);
		}

		$roleStorage = $this->entityTypeManager->getStorage('user_role');

		if (!$roleStorage->load($role)) {
			throw new \RuntimeException(
				sprintf('The Drupal role "%s" does not exist.', $role),
			);
		}

		$userStorage = $this->entityTypeManager->getStorage('user');

		$username = strtolower($firstName . '.' . $lastName) . '.'	. date('dmy');

		$email = strtolower($firstName . '.' . $lastName) . '@' . $role . '.com';

		$user = $userStorage->create([
			'name' => $username,
			'mail' => $email,
			'pass' => 'Pass@123',
			'status' => 1,
			'roles' => [$role],
		]);

		if (!$user->hasField('field_firstname')) {
			throw new \RuntimeException(
				'The field_firstname field does not exist on the user entity.',
			);
		}

		if (!$user->hasField('field_lastname')) {
			throw new \RuntimeException(
				'The field_lastname field does not exist on the user entity.',
			);
		}

		$user->set('field_firstname', $firstName);
		$user->set('field_lastname', $lastName);
		$user->save();

		return $user;
	}
}
