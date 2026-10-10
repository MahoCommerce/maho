<?php

/**
 * Shows the message and the status of a Mage_Core_Exception in a GraphQL error.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\GraphQl;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A service throws a Mage_Core_Exception for a client fault. GraphQL shows "Internal server error" for an
 * exception that is not an HTTP exception or a RuntimeException, so this normalizer gives the message and the
 * status that REST gives (see ApiExceptionListener).
 */
final class MageExceptionNormalizer implements NormalizerInterface
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        /** @var Error $data */
        /** @var \Mage_Core_Exception $exception supportsNormalization() accepts no other error */
        $exception = $data->getPrevious();
        $error = FormattedError::createFromException($data);
        $error['message'] = $exception->getMessage();
        $error['extensions']['status'] = ApiExceptionListener::mageExceptionStatus($exception);

        return $error;
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Error && $data->getPrevious() instanceof \Mage_Core_Exception;
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Error::class => false];
    }
}
