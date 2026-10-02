<?php

/**
 * Enables, disables, refreshes and flushes the cache types, as Mage_Adminhtml_CacheController does.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\DataObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        $user = $this->requireUser();
        $template = $operation instanceof HttpOperation ? (string) $operation->getUriTemplate() : '';

        if (str_ends_with($template, '/flush-all')) {
            return $this->flushAll($user);
        }

        $code = (string) ($uriVariables['code'] ?? '');
        $this->provider->cacheType($code);

        if (str_ends_with($template, '/refresh')) {
            return $this->refresh($code, $user);
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if (!array_key_exists('enabled', $body)) {
            throw ValidationException::requiredField('enabled');
        }
        if (!is_bool($body['enabled'])) {
            throw new ValidationException('enabled must be a boolean', 'enabled', 'Type');
        }

        return $this->setEnabled($code, $body['enabled'], $user);
    }

    private function setEnabled(string $code, bool $enabled, ApiUser $user): CacheType
    {
        $app = \Mage::app();
        $allTypes = $app->useCache();
        $allTypes = is_array($allTypes) ? $allTypes : [];
        $wasEnabled = !empty($allTypes[$code]);

        if ($wasEnabled !== $enabled) {
            $allTypes[$code] = $enabled ? 1 : 0;
            $app->saveUseCache($allTypes);
        }
        if (!$enabled) {
            $app->getCache()->cleanType($code);
        }

        $this->logApiActivity(
            'cache_type',
            'update',
            ['code' => $code, 'enabled' => $wasEnabled],
            new DataObject(['code' => $code, 'enabled' => $enabled]),
            $user,
        );

        return $this->provider->toCacheTypeDto(
            $this->provider->types()[$code],
            array_map(static fn(mixed $flag): bool => (bool) $flag, $allTypes),
        );
    }

    private function refresh(string $code, ApiUser $user): CacheType
    {
        \Mage::app()->getCache()->cleanType($code);
        \Mage::dispatchEvent('adminhtml_cache_refresh_type', ['type' => $code]);

        $this->logApiActivity('cache_type', 'refresh', ['code' => $code], new DataObject(['code' => $code]), $user);

        return $this->provider->cacheType($code);
    }

    private function flushAll(ApiUser $user): JsonResponse
    {
        \Mage::app()->getCache()->flush();
        \Mage::dispatchEvent('adminhtml_cache_flush_all');

        $count = count($this->provider->types());
        $this->logApiActivity('cache_type', 'flush_all', null, new DataObject(['flushed' => $count]), $user);

        return $this->respondRaw(['success' => true, 'flushed' => $count]);
    }
}
