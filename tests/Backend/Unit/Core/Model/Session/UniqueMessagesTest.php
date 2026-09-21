<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const STUCK_TEXT = '%s queue message(s) were claimed by a worker that never finished.';

function sessionNotice(string $text, string|\Maho\Message\Link ...$args): Mage_Core_Model_Message_Abstract
{
    return Mage::getSingleton('core/message')->notice($text, ...$args);
}

beforeEach(function (): void {
    $this->session = Mage::getSingleton('core/session');
    $this->session->getMessages(true);
});

it('keeps two messages that share a text but not their arguments', function () {
    $this->session->addUniqueMessages([sessionNotice(STUCK_TEXT, '3')]);
    $this->session->addUniqueMessages([sessionNotice(STUCK_TEXT, '7')]);

    $texts = array_map(
        static fn(Mage_Core_Model_Message_Abstract $m): string => $m->getText(),
        $this->session->getMessages()->getItems(),
    );

    expect($texts)->toBe([
        '3 queue message(s) were claimed by a worker that never finished.',
        '7 queue message(s) were claimed by a worker that never finished.',
    ]);
});

it('drops a message whose text and arguments both repeat', function () {
    $this->session->addUniqueMessages([sessionNotice(STUCK_TEXT, '3')]);
    $this->session->addUniqueMessages([sessionNotice(STUCK_TEXT, '3')]);

    expect($this->session->getMessages()->getItems())->toHaveCount(1);
});

it('still drops a plain duplicate that carries no arguments', function () {
    $this->session->addUniqueMessages([Mage::getSingleton('core/message')->notice('Same text.')]);
    $this->session->addUniqueMessages([Mage::getSingleton('core/message')->notice('Same text.')]);

    expect($this->session->getMessages()->getItems())->toHaveCount(1);
});

it('reads two links as one message when the reader sees the same words', function () {
    $this->session->addUniqueMessages([sessionNotice('Open %s.', new \Maho\Message\Link('here', '/a'))]);
    $this->session->addUniqueMessages([sessionNotice('Open %s.', new \Maho\Message\Link('here', '/b'))]);
    $this->session->addUniqueMessages([sessionNotice('Open %s.', new \Maho\Message\Link('there', '/b'))]);

    expect($this->session->getMessages()->getItems())->toHaveCount(2);
});
