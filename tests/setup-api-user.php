<?php

declare(strict_types=1);

/**
 * Maho API User Setup Script
 *
 * This script creates an API user and role for testing purposes.
 * Used in GitHub Actions and local development environments.
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require __DIR__ . '/../vendor/autoload.php';
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

// Get configuration from environment or use defaults
$config = [
    'username' => $_ENV['API_USERNAME'] ?? 'test_api_user',
    'password' => $_ENV['API_PASSWORD'] ?? 'test_api_password_123',
    'email' => $_ENV['API_EMAIL'] ?? 'api-test@maho.test',
    'firstname' => $_ENV['API_FIRSTNAME'] ?? 'Test',
    'lastname' => $_ENV['API_LASTNAME'] ?? 'ApiUser',
    'role_name' => $_ENV['API_ROLE_NAME'] ?? 'Pest Test API Role',
];

try {
    // Create API role with necessary permissions
    echo "Creating API role: {$config['role_name']}\n";

    /** @var Mage_Api_Model_Role $role */
    $role = Mage::getModel('api/role');

    // Check if role already exists
    $existingRole = Mage::getModel('api/role')->load($config['role_name'], 'role_name');
    if ($existingRole->getId()) {
        echo "Role already exists, deleting and recreating...\n";
        $existingRole->delete();
    }

    $role->setRoleName($config['role_name'])
         ->setRoleType('G')
         ->save();

    if (!$role->getId()) {
        throw new Exception('Failed to create API role');
    }

    echo "Created API role with ID: {$role->getId()}\n";

    // Set permissions for the role - allow access to all blog API resources
    echo "Setting role permissions...\n";

    /** @var Mage_Api_Model_Rules $rules */
    $rules = Mage::getModel('api/rules');
    $rules->setRoleId($role->getId())
          ->setResources(['all']) // Grant access to all API resources for testing
          ->saveRel();

    echo "Role permissions set successfully.\n";

    // Create API user
    echo "Creating API user: {$config['username']}\n";

    /** @var Mage_Api_Model_User $user */
    $user = Mage::getModel('api/user');

    // Check if user already exists
    $existingUser = Mage::getModel('api/user')->loadByUsername($config['username']);
    if ($existingUser->getId()) {
        echo "User already exists, deleting and recreating...\n";
        $existingUser->delete();
    }

    $user->setUsername($config['username'])
         ->setPassword($config['password'])
         ->setEmail($config['email'])
         ->setFirstname($config['firstname'])
         ->setLastname($config['lastname'])
         ->setIsActive(1)
         ->save();

    if (!$user->getId()) {
        throw new Exception('Failed to create API user');
    }

    echo "Created API user with ID: {$user->getId()}\n";

    // Assign role to user
    echo "Assigning role to user...\n";

    /** @var Mage_Api_Model_User $userRole */
    $userRoleModel = Mage::getModel('api/user');
    $userRoleModel->setUserId($user->getId())
                  ->setRoleIds([$role->getId()])
                  ->saveRelations();

    echo "Role assigned successfully.\n";

    // Verify the setup by attempting to get API resources
    echo "Verifying API setup...\n";

    try {
        /** @var Mage_Api_Model_Server $server */
        $server = Mage::getSingleton('api/server');
        $server->initialize('jsonrpc');

        echo "API server initialized successfully.\n";
    } catch (Exception $e) {
        echo 'WARNING: API server verification failed: ' . $e->getMessage() . "\n";
    }

    // Output configuration for use in tests
    echo "\n=== API Configuration ===\n";
    echo "API_USERNAME={$config['username']}\n";
    echo "API_PASSWORD={$config['password']}\n";
    echo "API_EMAIL={$config['email']}\n";
    echo "API_ROLE_ID={$role->getId()}\n";
    echo "API_USER_ID={$user->getId()}\n";
    echo "=============================\n";

    echo "\nAPI user setup completed successfully!\n";

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
