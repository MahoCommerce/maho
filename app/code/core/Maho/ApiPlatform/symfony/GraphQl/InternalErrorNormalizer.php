<?php

/**
 * Logs an exception that a GraphQL error shows as "Internal server error".
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\GraphQl;

use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The normalizers of priority -780 handle an HTTP exception, a RuntimeException and a Mage_Core_Exception.
 * Any other exception is a server fault. This normalizer logs it, as ApiExceptionListener does for REST,
 * and gives the same error as the ErrorNormalizer of API Platform.
 */
final class InternalErrorNormalizer implements NormalizerInterface
{
    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        /** @var Error $data */
        \Mage::logException($data->getPrevious());

        return FormattedError::createFromException($data);
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Error && $data->getPrevious() !== null;
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Error::class => false];
    }
}
