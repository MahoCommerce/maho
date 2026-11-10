<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\AdminUserChangepassword;
use MahoCLI\Commands\AdminUserCreate;
use MahoCLI\Commands\AdminUserDisable;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * System > Permissions > Users, System > My Account, the reset of a forgotten password and the admin:user:*
 * commands go through Mage_Admin_Service_User (#1348).
 */

const ADMIN_USER_PARITY_PASSWORD = 'pest-role-password-1234';

/**
 * @param array<string, mixed> $input
 * @return array{code: int, output: string}
 */
function adminUserParityCli(Command $command, array $input): array
{
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $code = $tester->execute($input, ['interactive' => false]);
    return ['code' => $code, 'output' => $tester->getDisplay()];
}

/**
 * @return array{success: list<string>, errors: list<string>}
 */
function adminUserParityAdmin(string $username, string $secret, string $mailbox): array
{
    return AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'save', [
        'username' => $username,
        'firstname' => 'Parity',
        'lastname' => 'User',
        'email' => $mailbox,
        'password' => $secret,
        'password_confirmation' => $secret,
        'is_active' => '1',
        'current_password' => ADMIN_USER_PARITY_PASSWORD,
    ]);
}

/**
 * @return array{code: int, output: string}
 */
function adminUserParityCreate(string $username, string $secret, string $mailbox): array
{
    return adminUserParityCli(new AdminUserCreate(), [
        'username' => $username,
        'password' => $secret,
        'email' => $mailbox,
        'firstname' => 'Parity',
        'lastname' => 'User',
    ]);
}

/**
 * Post a new password to System > My Account of the logged-in admin user $user.
 *
 * @return array{success: list<string>, errors: list<string>}
 */
function adminUserParityMyAccount(Mage_Admin_Model_User $user, string $secret, string $currentSecret = ADMIN_USER_PARITY_PASSWORD): array
{
    return AdminApiParity::admin(Mage_Adminhtml_System_AccountController::class, 'save', [
        'username' => $user->getUsername(),
        'firstname' => $user->getFirstname(),
        'lastname' => $user->getLastname(),
        'email' => $user->getEmail(),
        'current_password' => $currentSecret,
        'password_change' => '1',
        'new_password' => $secret,
        'password_confirmation' => $secret,
    ]);
}

/**
 * Give $user a password reset link, and post $secret to the page that the link opens.
 *
 * @return array{success: list<string>, errors: list<string>}
 */
function adminUserParityResetPassword(Mage_Admin_Model_User $user, string $secret): array
{
    $token = Mage::helper('admin')->generateResetPasswordLinkToken();
    $user->changeResetPasswordLinkToken($token)->save();

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create(
        'http://localhost/admin/index/resetPasswordPost',
        'POST',
        ['password' => $secret, 'confirmation' => $secret],
    ));
    $request->setRouteName('adminhtml')->setControllerName('index')->setActionName('resetPasswordPost')->setDispatched(true);
    Mage::app()->setRequest($request);
    AdminApiParity::adminMessages();
    // getQuery() reads $_GET
    $_GET = ['token' => $token, 'id' => (string) $user->getId()];
    $controller = new Mage_Adminhtml_IndexController($request, new Mage_Core_Controller_Response_Http());
    $controller->getLayout()->getMessagesBlock()->getMessageCollection()->clear();
    try {
        $controller->resetPasswordPostAction();
    } catch (Throwable) {
        // The redirect needs a dispatched request. The messages are the outcome
    } finally {
        $_GET = [];
    }
    $messages = AdminApiParity::adminMessages();
    // The form of the page takes the errors out of the session
    $layoutMessages = $controller->getLayout()->getMessagesBlock()->getMessageCollection();
    foreach ($layoutMessages->getErrors() as $error) {
        $messages['errors'][] = $error->getText();
    }
    $layoutMessages->clear();
    return $messages;
}

beforeEach(function (): void {
    $this->admin = AdminRoleFixture::login(['all']);
    Mage::app()->cleanCache([Maho\Security\RateLimiter::CACHE_TAG]);
});

afterEach(function (): void {
    Mage::getSingleton('adminhtml/session')->getMessages(true);
    AdminRoleFixture::delete($this->admin);
    foreach (Mage::getResourceModel('admin/user_collection')->addFieldToFilter('username', ['like' => 'parity_%']) as $user) {
        $user->delete();
    }
});

it('applies the password rules on the admin page and the CLI', function (): void {
    $message = Mage::helper('adminhtml')->__(
        'Password must be at least of %d characters.',
        Mage::getModel('admin/user')->getMinAdminPasswordLength(),
    );

    $admin = adminUserParityAdmin('parity_admin', 'abc1', 'parity-admin@example.test');
    $cli = adminUserParityCreate('parity_cli', 'abc1', 'parity-cli@example.test');

    expect($admin['errors'])->toContain($message)
        ->and($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message)
        ->and(Mage::getModel('admin/user')->loadByUsername('parity_cli')->getId())->toBeNull();

    $cli = adminUserParityCli(new AdminUserChangepassword(), ['username' => $this->admin->getUsername(), 'password' => 'abc1']);
    expect($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message);
});

it('applies the password rules on My Account, the password reset page and the CLI', function (): void {
    $message = Mage::helper('adminhtml')->__('Password must include both numeric and alphabetic characters.');

    $myAccount = adminUserParityMyAccount($this->admin, 'onlyletterspassword');
    $reset = adminUserParityResetPassword($this->admin, 'onlyletterspassword');
    $cli = adminUserParityCli(new AdminUserChangepassword(), ['username' => $this->admin->getUsername(), 'password' => 'onlyletterspassword']);

    expect($myAccount['errors'])->toBe([$message])
        ->and($reset['errors'])->toBe([$message])
        ->and($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message);
});

it('refuses an empty password on the password reset page and the CLI', function (): void {
    $message = Mage::helper('adminhtml')->__('New password field cannot be empty.');

    $reset = adminUserParityResetPassword($this->admin, '');
    $cli = adminUserParityCli(new AdminUserChangepassword(), ['username' => $this->admin->getUsername(), 'password' => ' ']);

    expect($reset['errors'])->toBe([$message])
        ->and($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message);
});

it('changes the password on My Account, the password reset page and the CLI', function (): void {
    $myAccount = adminUserParityMyAccount($this->admin, 'parity-account-1234');
    expect($myAccount['success'])->toBe([Mage::helper('adminhtml')->__('The account has been saved.')])
        ->and(Mage::helper('core')->validateHash('parity-account-1234', $this->admin->reload()->getPassword()))->toBeTrue();

    $reset = adminUserParityResetPassword($this->admin, 'parity-reset-1234');
    expect($reset['success'])->toBe([Mage::helper('adminhtml')->__('Your password has been updated.')])
        ->and(Mage::helper('core')->validateHash('parity-reset-1234', $this->admin->reload()->getPassword()))->toBeTrue();

    $cli = adminUserParityCli(new AdminUserChangepassword(), ['username' => $this->admin->getUsername(), 'password' => 'parity-cli-1234']);
    expect($cli['code'])->toBe(Command::SUCCESS)
        ->and(Mage::helper('core')->validateHash('parity-cli-1234', $this->admin->reload()->getPassword()))->toBeTrue();
});

it('ends a password reset link when the password changes on any page or the CLI', function (): void {
    $this->admin->changeResetPasswordLinkToken(Mage::helper('admin')->generateResetPasswordLinkToken())->save();

    $cli = adminUserParityCli(new AdminUserChangepassword(), ['username' => $this->admin->getUsername(), 'password' => 'parity-cli-1234']);

    expect($cli['code'])->toBe(Command::SUCCESS)
        ->and($this->admin->reload()->getRpToken())->toBeNull()
        ->and($this->admin->getRpTokenCreatedAt())->toBeNull();
});

it('refuses a user name or an email that another user has, on the admin page and the CLI', function (): void {
    $message = Mage::helper('adminhtml')->__('A user with the same user name or email already exists.');

    $admin = adminUserParityAdmin('parity_admin', 'parity-secret-1234', $this->admin->getEmail());
    $cli = adminUserParityCreate('parity_cli', 'parity-secret-1234', $this->admin->getEmail());

    expect($admin['errors'])->toBe([$message])
        ->and($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message);
});

it('refuses an empty email on the admin page and the CLI', function (): void {
    $message = Mage::helper('adminhtml')->__('Please enter a valid email.');

    $admin = adminUserParityAdmin('parity_admin', 'parity-secret-1234', '');
    $cli = adminUserParityCreate('parity_cli', 'parity-secret-1234', '');

    expect($admin['errors'])->toBe([$message])
        ->and($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message);
});

it('creates a user with a role on the admin page and the CLI', function (): void {
    $admin = adminUserParityAdmin('parity_admin', 'parity-secret-1234', 'parity-admin@example.test');
    $cli = adminUserParityCreate('parity_cli', 'parity-secret-1234', 'parity-cli@example.test');

    expect($admin['success'])->toBe([Mage::helper('adminhtml')->__('The user has been saved.')])
        ->and($cli['code'])->toBe(Command::SUCCESS)
        ->and(Mage::getModel('admin/user')->loadByUsername('parity_cli')->getRole()->getId())->not->toBeNull();
});

it('ends the admin session of a user that the CLI disables', function (): void {
    adminUserParityCreate('parity_cli', 'parity-secret-1234', 'parity-cli@example.test');
    $user = Mage::getModel('admin/user')->loadByUsername('parity_cli');

    expect(adminUserParityCli(new AdminUserDisable(), ['username' => 'parity_cli'])['code'])->toBe(Command::SUCCESS)
        ->and($user->reload()->getId())->toBeNull();
});

it('reports a user that does not exist on the admin page and the CLI', function (): void {
    $admin = AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'delete', [
        'user_id' => 999999,
        'current_password' => ADMIN_USER_PARITY_PASSWORD,
    ]);
    $cli = adminUserParityCli(new AdminUserDisable(), ['username' => 'parity_missing']);

    expect($admin['errors'])->toBe([Mage::helper('adminhtml')->__('This user no longer exists.')])
        ->and($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain(Mage::helper('adminhtml')->__('The user %s does not exist.', 'parity_missing'));
});

it('refuses to deactivate the own account of the admin user who saves it', function (): void {
    $admin = AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'save', [
        'user_id' => $this->admin->getId(),
        'username' => $this->admin->getUsername(),
        'firstname' => $this->admin->getFirstname(),
        'lastname' => $this->admin->getLastname(),
        'email' => $this->admin->getEmail(),
        'is_active' => '0',
        'current_password' => ADMIN_USER_PARITY_PASSWORD,
    ]);

    expect($admin['errors'])->toBe([Mage::helper('adminhtml')->__('You cannot deactivate your own account.')])
        ->and(Mage::getModel('admin/user')->load($this->admin->getId())->getIsActive())->toBeTrue();

    $service = Mage::getService('admin/user');
    $user = $service->getById((int) $this->admin->getId())->setIsActive(false);
    expect(fn() => $service->save($user, actingUserId: (int) $this->admin->getId()))
        ->toThrow(Mage_Core_Exception_Conflict::class);
});

it('lets an admin user deactivate the account of another user', function (): void {
    adminUserParityCreate('parity_cli', 'parity-secret-1234', 'parity-cli@example.test');
    $other = Mage::getModel('admin/user')->loadByUsername('parity_cli');

    $admin = AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'save', [
        'user_id' => $other->getId(),
        'username' => 'parity_cli',
        'firstname' => 'Parity',
        'lastname' => 'User',
        'email' => 'parity-cli@example.test',
        'is_active' => '0',
        'current_password' => ADMIN_USER_PARITY_PASSWORD,
    ]);

    expect($admin['success'])->toBe([Mage::helper('adminhtml')->__('The user has been saved.')])
        ->and($other->reload()->getIsActive())->toBeFalse();
});

it('refuses to delete the own account of the admin user who deletes it', function (): void {
    $admin = AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'delete', [
        'user_id' => $this->admin->getId(),
        'current_password' => ADMIN_USER_PARITY_PASSWORD,
    ]);

    expect($admin['errors'])->toBe([Mage::helper('adminhtml')->__('You cannot delete your own account.')])
        ->and(Mage::getModel('admin/user')->load($this->admin->getId())->getId())->not->toBeNull();

    $service = Mage::getService('admin/user');
    expect(fn() => $service->delete($service->getById((int) $this->admin->getId()), (int) $this->admin->getId()))
        ->toThrow(Mage_Core_Exception_Conflict::class);
});

it('lets an admin user delete the account of another user', function (): void {
    adminUserParityCreate('parity_cli', 'parity-secret-1234', 'parity-cli@example.test');
    $other = Mage::getModel('admin/user')->loadByUsername('parity_cli');

    $admin = AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'delete', [
        'user_id' => $other->getId(),
        'current_password' => ADMIN_USER_PARITY_PASSWORD,
    ]);

    expect($admin['success'])->toBe([Mage::helper('adminhtml')->__('The user has been deleted.')])
        ->and(Mage::getModel('admin/user')->loadByUsername('parity_cli')->getId())->toBeNull();
});

describe('kept difference: the admin page checks the person who uses it', function (): void {
    it('asks for the current password in the admin only', function (): void {
        $wrongPassword = AdminApiParity::admin(Mage_Adminhtml_Permissions_UserController::class, 'delete', [
            'user_id' => $this->admin->getId(),
            'current_password' => 'wrong-password-1234',
        ]);

        expect($wrongPassword['errors'])->toBe([Mage::helper('adminhtml')->__('Invalid current password.')]);
    });

    it('asks for the current password on My Account only', function (): void {
        $myAccount = adminUserParityMyAccount($this->admin, 'parity-account-1234', 'wrong-password-1234');

        expect($myAccount['errors'])->toBe([Mage::helper('adminhtml')->__('Invalid current password.')])
            ->and(Mage::helper('core')->validateHash(ADMIN_USER_PARITY_PASSWORD, $this->admin->reload()->getPassword()))->toBeTrue();
    });

    it('lets a caller without an acting admin user, such as the CLI, delete any account', function (): void {
        adminUserParityCreate('parity_cli', 'parity-secret-1234', 'parity-cli@example.test');
        $service = Mage::getService('admin/user');
        $service->delete($service->getByUsername('parity_cli'));

        expect(Mage::getModel('admin/user')->loadByUsername('parity_cli')->getId())->toBeNull();
    });

    it('lets the CLI, which has no acting admin user, disable any account', function (): void {
        expect(adminUserParityCli(new AdminUserDisable(), ['username' => $this->admin->getUsername()])['code'])->toBe(Command::SUCCESS)
            ->and(Mage::getModel('admin/user')->load($this->admin->getId())->getIsActive())->toBeFalse();
    });
});
