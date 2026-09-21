<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->session = Mage::getSingleton('newsletter/session');
    $this->session->getMessages(true);
});

it('stores an error in the shared message collection', function () {
    $this->session->addError('Subscription failed.');

    $items = $this->session->getMessages()->getItemsByType(Mage_Core_Model_Message::ERROR);

    expect($items)->toHaveCount(1)
        ->and(reset($items)->getText())->toBe('Subscription failed.');
});

it('keeps the url of a link argument', function () {
    $this->session->addSuccess('Open %s.', new \Maho\Message\Link('the list', '/newsletter'));

    $items = $this->session->getMessages()->getItemsByType(Mage_Core_Model_Message::SUCCESS);
    $message = reset($items);

    expect($message->getTextArgs()[0]->url)->toBe('/newsletter')
        ->and($message->getText())->toBe('Open the list.');
});

it('reads the oldest message of one type and removes it', function () {
    $this->session->addError('First.');
    $this->session->addError('Second.');

    expect($this->session->getError())->toBe('First.')
        ->and($this->session->getError())->toBe('Second.')
        ->and($this->session->getError())->toBe('');
});

it('reads an error and a success apart from each other', function () {
    $this->session->addError('Bad.');
    $this->session->addSuccess('Good.');

    expect($this->session->getSuccess())->toBe('Good.')
        ->and($this->session->getError())->toBe('Bad.');
});
