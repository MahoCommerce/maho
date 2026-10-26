<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\AdminUserChangepassword;
use MahoCLI\Commands\AdminUserCreate;
use MahoCLI\Commands\AdminUserDisable;
use MahoCLI\Commands\AdminUserEnable;
use MahoCLI\Commands\AdminUserTwofaReset;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tester\ExecutionResult;

uses(Tests\MahoBackendTestCase::class, ConsoleAssertionsTrait::class);

const ADMIN_USER_CLI_USERNAME = 'cli-admin';

function adminUserRun(Command $command, array $input = [], array $answers = []): ExecutionResult
{
    new Application()->addCommand($command);
    return new CommandTester($command)->run($input, $answers, interactive: $answers !== []);
}

function adminUserLoad(): Mage_Admin_Model_User
{
    return Mage::getModel('admin/user')->loadByUsername(ADMIN_USER_CLI_USERNAME);
}

function adminUserCleanup(): void
{
    $user = adminUserLoad();
    if ($user->getId()) {
        $user->delete();
    }
}

beforeEach(fn() => adminUserCleanup());
afterEach(fn() => adminUserCleanup());

it('creates an admin user from the arguments or from the answers in the terminal', function (array $input, array $answers): void {
    $result = adminUserRun(new AdminUserCreate('admin:user:create'), $input, $answers);

    $this->assertCommandIsSuccessful($result);
    $user = adminUserLoad();
    expect($user->getId())->not->toBeNull()
        ->and($user->getEmail())->toBe('cli-admin@example.com')
        ->and(Mage::helper('core')->validateHash('Password123!', $user->getPassword()))->toBeTrue();
})->with([
    'arguments' => [['username' => ADMIN_USER_CLI_USERNAME, 'password' => 'Password123!', 'email' => 'cli-admin@example.com', 'firstname' => 'Cli', 'lastname' => 'Admin'], []],
    'answers' => [[], [ADMIN_USER_CLI_USERNAME, 'Password123!', 'cli-admin@example.com', 'Cli', 'Admin']],
]);

it('changes the password, disables, enables and resets 2FA of the admin user that the argument names', function (): void {
    $this->assertCommandIsSuccessful(adminUserRun(new AdminUserCreate('admin:user:create'), [
        'username' => ADMIN_USER_CLI_USERNAME, 'password' => 'Password123!', 'email' => 'cli-admin@example.com', 'firstname' => 'Cli', 'lastname' => 'Admin',
    ]));
    adminUserLoad()->setTwofaEnabled(true)->setTwofaSecret('SECRET')->save();

    $this->assertCommandIsSuccessful(adminUserRun(new AdminUserChangepassword('admin:user:change-password'), ['username' => ADMIN_USER_CLI_USERNAME, 'password' => 'Changed456!']));
    expect(Mage::helper('core')->validateHash('Changed456!', adminUserLoad()->getPassword()))->toBeTrue();

    $this->assertCommandIsSuccessful(adminUserRun(new AdminUserDisable('admin:user:disable'), ['username' => ADMIN_USER_CLI_USERNAME]));
    expect(adminUserLoad()->getIsActive())->toBeFalse();

    $this->assertCommandIsSuccessful(adminUserRun(new AdminUserEnable('admin:user:enable'), ['username' => ADMIN_USER_CLI_USERNAME]));
    expect(adminUserLoad()->getIsActive())->toBeTrue();

    $this->assertCommandIsSuccessful(adminUserRun(new AdminUserTwofaReset('admin:user:twofa-reset'), ['username' => ADMIN_USER_CLI_USERNAME]));
    expect(adminUserLoad()->getTwofaEnabled())->toBeFalse();
});

it('fails without asking when the username is missing', function (): void {
    expect(fn() => adminUserRun(new AdminUserEnable('admin:user:enable')))
        ->toThrow(Symfony\Component\Console\Exception\RuntimeException::class, 'Not enough arguments (missing: "username")');
});
