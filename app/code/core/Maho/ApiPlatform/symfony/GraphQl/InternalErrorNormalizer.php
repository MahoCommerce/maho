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

use ApiPlatform\Validator\Exception\ConstraintViolationListAwareExceptionInterface;
use GraphQL\Error\ClientAware;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * An HTTP exception, a validation exception, a Mage_Core_Exception, a NotNormalizableValueException (a value of
 * the wrong type) and an exception that GraphQL marks as safe for the client are client faults, and other normalizers show them. Any other exception is a server fault.
 * This normalizer logs it, as ApiExceptionListener does for REST, and gives the same error as the ErrorNormalizer
 * of API Platform. It runs before the RuntimeExceptionNormalizer of API Platform, which shows the message of a
 * RuntimeException to the client and does not log it.
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
        if (!$data instanceof Error) {
            return false;
        }
        $exception = $data->getPrevious();
        return $exception !== null
            && !$exception instanceof HttpExceptionInterface
            && !$exception instanceof ConstraintViolationListAwareExceptionInterface
            && !$exception instanceof \Mage_Core_Exception
            && !$exception instanceof NotNormalizableValueException
            && !($exception instanceof ClientAware && $exception->isClientSafe());
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Error::class => false];
    }
}
