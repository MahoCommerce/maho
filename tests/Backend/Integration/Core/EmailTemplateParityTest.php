<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Core\Api\EmailTemplate;
use Mage\Core\Api\EmailTemplateProcessor;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin page and the API save an email template through Mage_Core_Service_Email_Template (#1348).
 * Each test gives the same template to both transports and compares the outcome.
 */

const EMAIL_TEMPLATE_PARITY_PATH = 'contacts/email/email_template';

/**
 * @param array<string, mixed> $fields the fields of the admin form
 * @return array{success: list<string>, errors: list<string>}
 */
function emailTemplateViaAdmin(array $fields, string $action = 'save'): array
{
    Mage::unregister('email_template');
    Mage::unregister('current_email_template');
    return AdminApiParity::admin(Mage_Adminhtml_System_Email_TemplateController::class, $action, $fields);
}

/**
 * @param array<string, mixed> $body the JSON body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function emailTemplateViaApi(array $body, ?int $id = null, bool $delete = false): array
{
    $method = $delete ? 'DELETE' : ($id ? 'PUT' : 'POST');
    $request = AdminApiParity::jsonRequest($method, '/api/rest/v2/email-templates' . ($id ? "/{$id}" : ''), $body);
    $dto = new EmailTemplate();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    $operation = match ($method) {
        'DELETE' => new Delete(),
        'PUT' => new Put(),
        default => new Post(),
    };
    return AdminApiParity::api($request, fn() => new EmailTemplateProcessor(AdminApiParity::adminSecurity())
        ->process($delete ? null : $dto, $operation, $id ? ['id' => $id] : [], ['request' => $request]));
}

function emailTemplateParityId(string $code): ?int
{
    $id = Mage::getModel('core/email_template')->getCollection()->addFieldToFilter('template_code', $code)->getFirstItem()->getId();
    return $id === null ? null : (int) $id;
}

function emailTemplateParityUsages(int $id): int
{
    return Mage::getResourceModel('core/config_data_collection')
        ->addFieldToFilter('path', EMAIL_TEMPLATE_PARITY_PATH)
        ->addFieldToFilter('value', (string) $id)
        ->getSize();
}

function emailTemplateParityCleanup(): void
{
    $collection = Mage::getModel('core/email_template')->getCollection()
        ->addFieldToFilter('template_code', ['like' => 'Pest parity %']);
    foreach ($collection as $template) {
        $template->delete();
    }
}

beforeEach(function () {
    emailTemplateParityCleanup();
});

afterEach(function () {
    emailTemplateParityCleanup();
});

it('saves the same template on both transports', function () {
    $admin = emailTemplateViaAdmin(['template_code' => ' Pest parity admin ', 'template_subject' => 'Hello', 'template_text' => '<p>{{var name}}</p>']);
    $api = emailTemplateViaApi(['templateCode' => ' Pest parity api ', 'templateSubject' => 'Hello', 'templateText' => '<p>{{var name}}</p>']);

    $saved = Mage::getModel('core/email_template')->load(emailTemplateParityId('Pest parity admin'));
    expect($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The email template has been saved.'])
        ->and((int) $saved->getData('template_type'))->toBe(Mage_Core_Model_Template::TYPE_HTML)
        ->and($saved->getAddedAt())->not->toBeNull()
        ->and($api['status'])->toBe(200)
        ->and($api['result']->templateCode)->toBe('Pest parity api')
        ->and($api['result']->templateType)->toBe('html')
        ->and($api['result']->addedAt)->not->toBeNull();
});

it('rejects a name that another template has on both transports', function () {
    emailTemplateViaApi(['templateCode' => 'Pest parity taken', 'templateSubject' => 'Hello', 'templateText' => 'Body']);

    $message = "An email template with code 'Pest parity taken' already exists";
    $admin = emailTemplateViaAdmin(['template_code' => 'Pest parity taken', 'template_subject' => 'Hello', 'template_text' => 'Body']);
    $api = emailTemplateViaApi(['templateCode' => 'Pest parity taken', 'templateSubject' => 'Hello', 'templateText' => 'Body']);

    expect($admin['errors'])->toBe([$message])
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe([$message]);
});

it('gives the same field errors on both transports, and refuses HTML outside the body', function () {
    $admin = emailTemplateViaAdmin(['template_code' => 'Pest parity bad', 'template_subject' => '<b>Hello</b>', 'template_text' => ' ']);
    $api = emailTemplateViaApi(['templateCode' => 'Pest parity bad', 'templateSubject' => '<b>Hello</b>', 'templateText' => ' ']);

    expect($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['templateText', 'templateSubject'])
        ->and($admin['errors'])->toBe($api['errors'])
        ->and(emailTemplateParityId('Pest parity bad'))->toBeNull();
});

it('answers a missing template as not found on both transports', function () {
    $admin = emailTemplateViaAdmin(['id' => 999999999, 'template_code' => 'Pest parity missing', 'template_subject' => 'Hello', 'template_text' => 'Body']);
    $api = emailTemplateViaApi(['templateSubject' => 'Hello'], 999999999);

    expect($admin['errors'])->toBe(['This Email template no longer exists.'])
        ->and($api['status'])->toBe(404);
});

it('resets the config path that uses a deleted template on both transports', function () {
    $config = Mage::getModel('core/config');
    foreach (['admin', 'api'] as $transport) {
        emailTemplateViaApi(['templateCode' => "Pest parity {$transport} delete", 'templateSubject' => 'Hello', 'templateText' => 'Body']);
        $id = (int) emailTemplateParityId("Pest parity {$transport} delete");
        $config->saveConfig(EMAIL_TEMPLATE_PARITY_PATH, (string) $id, 'default', 0);

        $transport === 'admin'
            ? emailTemplateViaAdmin(['id' => $id], 'delete')
            : emailTemplateViaApi([], $id, delete: true);

        expect(emailTemplateParityId("Pest parity {$transport} delete"))->toBeNull()
            ->and(emailTemplateParityUsages($id))->toBe(0);
    }
});
