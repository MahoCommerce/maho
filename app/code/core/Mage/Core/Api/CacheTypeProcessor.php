<?php

/**
 * Give the cache type operations of the API to the cache service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\DataObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CacheTypeProcessor extends \Maho\ApiPlatform\Processor
{
    public function __construct(
        Security $security,
        private readonly CacheTypeProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CacheType|JsonResponse
    {
        $this->requireUser();
        $service = CacheTypeProvider::service();
        $name = $operation->getName();

        if ($name === 'cache_type_flush_all') {
            $service->flush();
            $count = count($service->getTypes());
            $this->logApiActivity('cache_type', 'flush_all', null, new DataObject(['flushed' => $count]));
            return $this->respondRaw(['success' => true, 'flushed' => $count]);
        }

        $code = (string) ($uriVariables['code'] ?? '');
        if ($name === 'cache_type_refresh') {
            $service->refresh([$code]);
            $this->logApiActivity('cache_type', 'refresh', ['code' => $code], new DataObject(['code' => $code]));
            return $this->provider->toCacheTypeDto($service->getByCode($code));
        }

        $wasEnabled = (bool) $service->getByCode($code)->getData('status');
        $body = $this->parseRequestBody($context['request'] ?? null);
        if (!array_key_exists('enabled', $body)) {
            throw ValidationException::requiredField('enabled');
        }
        if (!is_bool($body['enabled'])) {
            throw new BadRequestHttpException('enabled must be a boolean');
        }

        if ($body['enabled']) {
            $service->enable([$code]);
        } else {
            $service->disable([$code]);
        }
        $this->logApiActivity(
            'cache_type',
            'update',
            ['code' => $code, 'enabled' => $wasEnabled],
            new DataObject(['code' => $code, 'enabled' => $body['enabled']]),
        );

        return $this->provider->toCacheTypeDto($service->getByCode($code));
    }
}
