<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function noticeWith(string $text, string|\Maho\Message\Link|null ...$args): Mage_Core_Model_Message_Abstract
{
    return Mage::getSingleton('core/message')->notice($text, ...$args);
}

it('returns the text unchanged when no argument was given', function () {
    expect(Mage::getSingleton('core/message')->notice('Plain text.')->getText())->toBe('Plain text.');
});

it('replaces the placeholders with the arguments', function () {
    expect(noticeWith('Tag "%s" was added to %s.', 'sale', 'the product')->getText())
        ->toBe('Tag "sale" was added to the product.');
});

it('reduces a link argument to its label', function () {
    expect(noticeWith('Open %s.', new \Maho\Message\Link('the queue grid', '/admin/queue'))->getText())
        ->toBe('Open the queue grid.');
});

it('renders a null argument as an empty string', function () {
    expect(noticeWith('Name: %s.', null)->getText())->toBe('Name: .');
});

it('does not escape, because the text is plain and the renderer escapes it', function () {
    expect(noticeWith('Tag "%s".', '<b>x</b>')->getText())->toBe('Tag "<b>x</b>".');
});

it('falls back to the raw text when the format string is wrong', function () {
    expect(noticeWith('100% wrong: %s', 'value')->getText())->toBe('100% wrong: %s')
        ->and(noticeWith('Wanted %s and %s', 'only one')->getText())->toBe('Wanted %s and %s');
});
