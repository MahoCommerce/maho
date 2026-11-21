<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Logger;

uses(Tests\MahoBackendTestCase::class);

it('logs which required field is missing when a transactional email cannot be sent', function (string $field, string $label): void {
    $handler = new TestHandler();
    $loggers = new ReflectionProperty(Mage_Core_Model_Logger::class, '_loggers');
    $previousLoggers = $loggers->getValue();
    $loggers->setValue(null, ['exception.log' => new Logger('test', [$handler])] + $previousLoggers);
    Mage::app()->getStore()->setConfig('dev/log/active', '1');

    try {
        $template = $this->createPartialMock(Mage_Core_Model_Email_Template::class, ['getProcessedTemplate']);
        $template->expects($this->never())->method('getProcessedTemplate');
        $template->setSenderName('Example shop')
            ->setSenderEmail('sender@example.test')
            ->setTemplateSubject('Example subject')
            ->unsetData($field);

        expect($template->send('recipient@example.test'))->toBeFalse();
    } finally {
        $loggers->setValue(null, $previousLoggers);
    }

    expect($handler->getRecords())->toHaveCount(1)
        ->and($handler->getRecords()[0]->message)->toBe("This letter cannot be sent. Missing $label.");
})->with([
    'sender name' => ['sender_name', 'sender name'],
    'sender email' => ['sender_email', 'sender email'],
    'subject' => ['template_subject', 'subject'],
]);
