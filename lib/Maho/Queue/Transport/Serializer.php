<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue\Transport;

use Maho\Queue\HandlerRegistry;
use Maho\Queue\Stamp\TraceContextStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Serializes only the message object, never the envelope, and refuses to
 * unserialize any class that is not a registered message class. Messages must
 * be flat DTOs of scalars, arrays, and DateTimeImmutable/DateTimeZone values.
 */
final class Serializer implements SerializerInterface
{
    /** Storage limit of the trace_context column; an oversized carrier is dropped, not truncated */
    public const TRACE_CONTEXT_MAX_LENGTH = 1024;

    /**
     * @return array{body: string, headers: array<string, string>}
     */
    #[\Override]
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        $headers = ['type' => $message::class];

        $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
        if ($retryCount > 0) {
            $headers['retries'] = (string) $retryCount;
        }

        $carrier = $envelope->last(TraceContextStamp::class)?->carrier;
        if ($carrier) {
            $encoded = json_encode($carrier);
            if (is_string($encoded) && strlen($encoded) <= self::TRACE_CONTEXT_MAX_LENGTH) {
                $headers['trace_context'] = $encoded;
            }
        }

        return ['body' => serialize($message), 'headers' => $headers];
    }

    #[\Override]
    public function decode(array $encodedEnvelope): Envelope
    {
        $type = $encodedEnvelope['headers']['type'] ?? null;
        $body = $encodedEnvelope['body'];
        if (!is_string($type)) {
            throw new MessageDecodingFailedException('Encoded envelope is missing its type header');
        }

        $allowed = HandlerRegistry::allowedMessageClasses();
        if (!in_array($type, $allowed, true)) {
            throw new MessageDecodingFailedException(sprintf('Refusing to decode message of class "%s": no registered message handler', $type));
        }

        $message = unserialize($body, [
            'allowed_classes' => [...$allowed, \DateTimeImmutable::class, \DateTimeZone::class],
        ]);
        if (!$message instanceof $type) {
            throw new MessageDecodingFailedException(sprintf('Stored body did not unserialize to an instance of "%s"', $type));
        }

        $stamps = [];
        $retries = (int) ($encodedEnvelope['headers']['retries'] ?? 0);
        if ($retries > 0) {
            $stamps[] = new RedeliveryStamp($retries);
        }

        $traceContext = $encodedEnvelope['headers']['trace_context'] ?? null;
        if (is_string($traceContext) && $traceContext !== '') {
            $carrier = json_decode($traceContext, true);
            if (is_array($carrier) && $carrier !== []) {
                $stamps[] = new TraceContextStamp(array_map(strval(...), $carrier));
            }
        }

        return new Envelope($message, $stamps);
    }
}
