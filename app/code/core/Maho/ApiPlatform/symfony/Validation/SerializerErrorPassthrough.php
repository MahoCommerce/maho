<?php

/**
 * Leaves the errors of the serializer to ApiExceptionListener.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Validation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\DenormalizationViolationFactoryInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

/**
 * API Platform turns a value of a wrong type into a 422. A body that does not match its type is a request that the
 * server cannot read, so the exception goes on to ApiExceptionListener, which answers 400 with every field.
 */
final class SerializerErrorPassthrough implements DenormalizationViolationFactoryInterface
{
    #[\Override]
    public function handle(NotNormalizableValueException|PartialDenormalizationException $exception, Operation $operation): void {}
}
