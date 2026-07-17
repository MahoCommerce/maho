<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Return a bare JSON body from a provider/processor instead of the resource's
 * JSON-LD serialization.
 *
 * The default API Platform pipeline serializes whatever a provider/processor
 * returns as the resource type (JSON-LD / Hydra, with an `@id` IRI). Some
 * focused sub-resource endpoints intentionally return a *different* shape — a
 * flat totals object, a plain list of shipping/payment methods — that isn't the
 * resource and has no IRI. Returning a `Response` from the provider/processor
 * makes API Platform pass it through untouched, bypassing normalization (and the
 * IRI generation that would otherwise 500 on a shapeless payload).
 *
 * Widen the method's return type to `<Resource>|Response` (or `|Response|null`)
 * when you use this, so the signature stays honest.
 *
 *   return $this->respondRaw($this->cartMapper->mapPricesToArray($quote));
 */
trait RawResponseTrait
{
    protected function respondRaw(mixed $data, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status);
    }
}
