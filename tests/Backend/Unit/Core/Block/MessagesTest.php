<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

$payload = 'Gift card "<img src=x onerror=alert(1)>" is not valid.';

beforeEach(function (): void {
    $this->block = Mage::app()->getLayout()->createBlock('core/messages');
});

it('escapes message text by default in grouped html', function () use ($payload) {
    $this->block->addError($payload);

    $html = $this->block->getGroupedHtml();

    expect($html)->not->toContain('<img')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('escapes message text by default in flat html', function () use ($payload) {
    $this->block->addError($payload);

    $html = $this->block->getHtml();

    expect($html)->not->toContain('<img')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('escapes messages coming from a session storage', function () use ($payload) {
    $session = Mage::getSingleton('core/session');
    $session->getMessages(true);
    $session->addError($payload);

    $this->block->addMessages($session->getMessages(true));

    expect($this->block->getGroupedHtml())->not->toContain('<img');
});

it('keeps markup of a message that explicitly allows html', function () {
    $this->block->addSuccess('Please <a href="/customer/account/">click here</a>.', true);

    expect($this->block->getGroupedHtml())->toContain('<a href="/customer/account/">click here</a>');
});

it('keeps markup of a session message that explicitly allows html', function () {
    $session = Mage::getSingleton('core/session');
    $session->getMessages(true);
    $session->addNotice('Client ID: abc<br/>Client Secret: def', true);

    $this->block->addMessages($session->getMessages(true));

    expect($this->block->getGroupedHtml())->toContain('abc<br/>Client Secret');
});

it('still escapes other messages when one allows html', function () use ($payload) {
    $this->block->addSuccess('Done. <a href="/">Continue</a>', true);
    $this->block->addError($payload);

    $html = $this->block->getGroupedHtml();

    expect($html)->toContain('<a href="/">Continue</a>')
        ->and($html)->not->toContain('<img');
});

it('lets a block opt out of escaping for every message', function () use ($payload) {
    $this->block->setEscapeMessageFlag(false);
    $this->block->addError($payload);

    expect($this->block->getGroupedHtml())->toContain('<img src=x onerror=alert(1)>');
});

it('marks a message as html only on request', function () {
    $message = Mage::getSingleton('core/message')->notice('text');

    expect($message->getAllowHtml())->toBeFalse()
        ->and($message->setAllowHtml()->getAllowHtml())->toBeTrue();
});
