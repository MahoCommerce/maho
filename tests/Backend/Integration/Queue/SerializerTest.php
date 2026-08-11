<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\Transport\Serializer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

uses(Tests\MahoBackendTestCase::class);

it('roundtrips a registered message class', function () {
    $serializer = new Serializer();
    $encoded = $serializer->encode(new Envelope(makeEmailMessage('roundtrip')));

    expect($encoded['headers']['type'])->toBe(Mage_Core_Model_Email_SendMessage::class);

    $decoded = $serializer->decode($encoded);
    $message = $decoded->getMessage();
    expect($message)->toBeInstanceOf(Mage_Core_Model_Email_SendMessage::class);
    expect($message->subject)->toBe('roundtrip');
});

it('carries the retry count through encode and decode', function () {
    $serializer = new Serializer();
    $encoded = $serializer->encode(new Envelope(makeEmailMessage(), [new RedeliveryStamp(2)]));
    expect($encoded['headers']['retries'])->toBe('2');

    $decoded = $serializer->decode($encoded);
    expect($decoded->last(RedeliveryStamp::class)?->getRetryCount())->toBe(2);
});

it('refuses to decode a class without a registered handler', function () {
    $serializer = new Serializer();

    expect(fn() => $serializer->decode([
        'body' => serialize(new stdClass()),
        'headers' => ['type' => stdClass::class],
    ]))->toThrow(MessageDecodingFailedException::class);
});

it('refuses a body that does not unserialize to the declared type', function () {
    $serializer = new Serializer();

    expect(fn() => $serializer->decode([
        'body' => serialize(new stdClass()),
        'headers' => ['type' => Mage_Core_Model_Email_SendMessage::class],
    ]))->toThrow(MessageDecodingFailedException::class);
});
