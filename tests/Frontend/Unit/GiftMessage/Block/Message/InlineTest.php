<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

it('escapes the default value when the gift message field is empty', function () {
    $block = Mage::app()->getLayout()->createBlock('giftmessage/message_inline');

    expect($block->getEscaped('', 'Jane" onfocus="alert(1)'))->toBe('Jane&quot; onfocus=&quot;alert(1)')
        ->and($block->getEscaped(null, '<b>Jane</b>'))->toBe('&lt;b&gt;Jane&lt;/b&gt;')
        ->and($block->getEscaped(' <i>x</i> ', 'default'))->toBe('&lt;i&gt;x&lt;/i&gt;');
});
