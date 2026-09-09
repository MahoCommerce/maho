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

it('lets a block opt out of escaping for every message', function () use ($payload) {
    $this->block->setEscapeMessageFlag(false);
    $this->block->addError($payload);

    expect($this->block->getGroupedHtml())->toContain('<img src=x onerror=alert(1)>');
});

describe('plain text messages', function () {
    it('escapes markup and single quotes in an argument', function () {
        $this->block->addNoticeText('Tag "%s" was added.', '<b>x</b> it\'s \'quoted\'');

        $html = $this->block->getGroupedHtml();

        expect($html)->not->toContain('<b>')
            ->and($html)->toContain('&lt;b&gt;x&lt;/b&gt; it&#039;s &#039;quoted&#039;');
    });

    it('escapes markup in the text itself', function () {
        $this->block->addErrorText('<script>alert(1)</script> failed');

        expect($this->block->getGroupedHtml())->not->toContain('<script');
    });

    it('renders a link argument as an anchor', function () {
        $this->block->addSuccessText(
            'Done. %s to continue.',
            new Maho\Message\Link('Click here', '/customer/account/?a=1&b=2'),
        );

        expect($this->block->getGroupedHtml())
            ->toContain('<a href="/customer/account/?a=1&amp;b=2">Click here</a>');
    });

    it('escapes the label and the url of a link argument', function () {
        $this->block->addSuccessText(
            '%s',
            new Maho\Message\Link('<b>label</b>', '/x?q=\'" onmouseover="alert(1)'),
        );

        $html = $this->block->getGroupedHtml();

        expect($html)->toContain('&lt;b&gt;label&lt;/b&gt;')
            ->and($html)->not->toContain('onmouseover="alert(1)"')
            ->and($html)->not->toContain('<b>label</b>');
    });

    it('neutralises a javascript scheme in a link argument', function () {
        $this->block->addNoticeText(
            'Open %s.',
            new Maho\Message\Link('here', 'javascript:alert(1)'),
        );

        expect($this->block->getGroupedHtml())->toContain('<a href=":alert(1)">here</a>')
            ->and($this->block->getGroupedHtml())->not->toContain('javascript:');
    });

    it('neutralises a data scheme in a link argument', function () {
        $this->block->addNoticeText(
            'Open %s.',
            new Maho\Message\Link('here', 'data:text/html,<script>alert(1)</script>'),
        );

        expect($this->block->getGroupedHtml())->not->toContain('data:text/html');
    });

    it('renders a newline as a line break', function () {
        $this->block->addNoticeText("Client ID: %s\nClient Secret: %s", 'id', 'secret');

        expect($this->block->getGroupedHtml())->toContain("Client ID: id<br>\nClient Secret: secret");
    });

    it('does not throw when the text expects more arguments than it gets', function () {
        $this->block->addErrorText('Wanted %s and %s', 'only one');

        $html = $this->block->getGroupedHtml();

        expect($html)->toContain('Wanted %s and %s');
    });

    it('does not throw on an unknown format specifier', function () {
        $this->block->addErrorText('100% wrong: %s', 'value');

        expect(fn() => $this->block->getGroupedHtml())->not->toThrow(Throwable::class);
    });

    it('escapes an argument that is not a string', function () {
        $this->block->addNoticeText('%s message(s) stuck.', 5);

        expect($this->block->getGroupedHtml())->toContain('5 message(s) stuck.');
    });
});

it('refuses an argument that is not a scalar or a link', function () {
    expect(fn() => $this->block->addNoticeText('%s', ['an', 'array']))->toThrow(TypeError::class);
});

it('logs and keeps the raw text when a bad argument reaches the renderer', function () {
    $message = Mage::getSingleton('core/message')->notice('%s');
    (new ReflectionProperty($message, '_textArgs'))->setValue($message, [new stdClass()]);
    $this->block->addMessage($message);

    expect($this->block->getGroupedHtml())->toContain('%s');
});
