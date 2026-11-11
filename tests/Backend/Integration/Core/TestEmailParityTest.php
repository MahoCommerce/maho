<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\EmailTestSend;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The Send Test Email button of System > Configuration and the email:test:send command send the test email through
 * Mage_Core_Service_Email_Template::sendTest() (#1348). Each test gives the same recipient to both transports.
 */

/**
 * @return array{success: bool, message: string}
 */
function testEmailViaAdmin(string $recipient): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('http://localhost/admin/system_config_testemail/send', 'POST', ['recipient' => $recipient]),
    );
    $request->setRouteName('adminhtml')->setControllerName('system_config_testemail')->setActionName('send')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Adminhtml_System_Config_TestEmailController($request, $response)->sendAction();
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return array{code: int, output: string}
 */
function testEmailViaCli(string $recipient): array
{
    $command = new EmailTestSend();
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $code = $tester->execute(['recipient' => $recipient], ['interactive' => false]);
    return ['code' => $code, 'output' => $tester->getDisplay()];
}

/**
 * Register a service whose mail transport records each recipient, and returns $sent.
 */
function testEmailSpy(bool $sent): object
{
    $spy = new class ($sent) extends Mage_Core_Service_Email_Template {
        /** @var list<array{recipient: string, subject: string}> */
        public array $sentTo = [];

        /** @var list<array{recipient: string, subject: string}> */
        public array $queued = [];

        public function __construct(private readonly bool $sent) {}

        #[\Override]
        protected function sendTemplate(Mage_Core_Model_Email_Template $template, string $recipient): bool
        {
            $this->sentTo[] = ['recipient' => $recipient, 'subject' => (string) $template->getTemplateSubject()];
            return $this->sent;
        }

        #[\Override]
        protected function queueTemplate(Mage_Core_Model_Email_Template $template, string $recipient): bool
        {
            $this->queued[] = ['recipient' => $recipient, 'subject' => (string) $template->getTemplateSubject()];
            return $this->sent;
        }
    };
    Mage::unregister('_service/core/email_template');
    Mage::register('_service/core/email_template', $spy);
    return $spy;
}

beforeEach(function (): void {
    $this->developerMode = Mage::getIsDeveloperMode();
    Mage::app()->getStore()->setConfig('system/smtp/enabled', 'smtp');
    Mage::app()->getStore()->setConfig('system/smtp/host', '127.0.0.1');
    Mage::app()->getStore()->setConfig('system/smtp/port', '2525');
});

afterEach(function (): void {
    Mage::setIsDeveloperMode($this->developerMode);
    Mage::unregister('_service/core/email_template');
});

it('refuses an invalid or an empty recipient on the admin and the CLI', function (string $recipient): void {
    $spy = testEmailSpy(true);
    $message = Mage::helper('core')->__('Invalid email address.');

    $admin = testEmailViaAdmin($recipient);
    $cli = testEmailViaCli($recipient);

    expect($admin)->toBe(['success' => false, 'message' => $message])
        ->and($cli['code'])->toBe(Command::INVALID)
        ->and($cli['output'])->toContain($message)
        ->and($spy->sentTo)->toBe([]);
})->with(['not-an-email', '']);

it('refuses to send when the store has no mail transport, on the admin and the CLI', function (): void {
    $spy = testEmailSpy(true);
    Mage::app()->getStore()->setConfig('system/smtp/enabled', 'disabled');
    $message = Mage::helper('core')->__('Email transport is disabled. Please configure an email transport in Mail Sending Settings.');

    $admin = testEmailViaAdmin('parity@example.test');
    $cli = testEmailViaCli('parity@example.test');

    expect($admin)->toBe(['success' => false, 'message' => $message])
        ->and($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain($message)
        ->and($spy->sentTo)->toBe([]);
});

it('sends the same test email from the admin and the CLI', function (): void {
    $spy = testEmailSpy(true);

    $admin = testEmailViaAdmin('parity@example.test');
    $cli = testEmailViaCli('parity@example.test');

    $email = ['recipient' => 'parity@example.test', 'subject' => Mage::helper('core')->__('Maho Test Email')];
    expect($admin['success'])->toBeTrue()
        ->and($cli['code'])->toBe(Command::SUCCESS)
        ->and($spy->sentTo)->toBe([$email, $email]);
});

it('reports a mail transport that does not send the email as a server fault, on the admin and the CLI', function (): void {
    testEmailSpy(false);
    $message = Mage::helper('core')->__('Failed to send test email. Please check your email configuration and error logs.');

    $admin = testEmailViaAdmin('parity@example.test');

    // The console application prints an exception that the command does not catch, and exits with 1
    expect($admin)->toBe(['success' => false, 'message' => Mage::helper('adminhtml')->__('An error occurred: %s', $message)])
        ->and(fn() => testEmailViaCli('parity@example.test'))->toThrow(RuntimeException::class, $message)
        ->and(fn() => testEmailViaQueueCli('parity@example.test'))
        ->toThrow(RuntimeException::class, Mage::helper('core')->__('Could not add the test email to the queue. Please check the error logs.'));
});

it('sends the test email without the queue and restores the developer mode', function (): void {
    Mage::setIsDeveloperMode(false);
    $template = Mage::getModel('core/email_template')
        ->setSenderName('Parity')
        ->setSenderEmail('sender@example.test')
        ->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT)
        ->setTemplateText('Parity')
        ->setTemplateSubject('Parity');
    $service = new class extends Mage_Core_Service_Email_Template {
        public function deliver(Mage_Core_Model_Email_Template $template, string $recipient): bool
        {
            return $this->sendTemplate($template, $recipient);
        }
    };

    // Nothing listens on the port, so a direct send fails. A queued send would succeed
    expect($service->deliver($template, 'parity@example.test'))->toBeFalse()
        ->and(Mage::getIsDeveloperMode())->toBeFalse();
});

/**
 * @return array{code: int, output: string}
 */
function testEmailViaQueueCli(string $recipient): array
{
    $command = new \MahoCLI\Commands\EmailTestQueue();
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $code = $tester->execute(['recipient' => $recipient], ['interactive' => false]);
    return ['code' => $code, 'output' => $tester->getDisplay()];
}

it('queues the test email of email:test:queue with the checks and the text of email:test:send', function (): void {
    $spy = testEmailSpy(true);

    $invalid = testEmailViaQueueCli('not-an-address');
    $queued = testEmailViaQueueCli('queue@example.test');
    testEmailViaCli('queue@example.test');
    Mage::app()->getStore()->setConfig('system/smtp/enabled', 'disabled');
    $noTransport = testEmailViaQueueCli('queue@example.test');

    expect($invalid['code'])->toBe(Command::INVALID)
        ->and($invalid['output'])->toContain('Invalid email address.')
        ->and($queued['code'])->toBe(Command::SUCCESS)
        ->and($spy->queued)->toBe($spy->sentTo)
        ->and($spy->queued)->toHaveCount(1)
        ->and($noTransport['code'])->toBe(Command::FAILURE);
});
