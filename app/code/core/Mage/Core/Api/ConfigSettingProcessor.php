<?php

/**
 * Writes one system configuration field at one scope, or removes its own value.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\DataObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @phpstan-import-type FieldInfo from \Mage_Core_Service_Config
 * @phpstan-import-type ScopeInfo from ConfigSettingProvider
 */
final class ConfigSettingProcessor extends \Maho\ApiPlatform\Processor
{
    public function __construct(
        Security $security,
        private readonly ConfigSettingProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ConfigSetting
    {
        $user = $this->requireUser();
        $body = $operation instanceof DeleteOperationInterface ? [] : $this->parseRequestBody($context['request'] ?? null);
        $params = $body + $this->provider->requestFilters($context);

        $service = $this->provider->service();
        $field = $service->getField((string) ($uriVariables['path'] ?? $params['path'] ?? ''));
        $scope = $this->provider->resolveScope(
            isset($params['scope']) ? (string) $params['scope'] : ConfigSetting::SCOPE_DEFAULT,
            isset($params['scopeCode']) ? (string) $params['scopeCode'] : null,
            array_key_exists('scope', $body) || array_key_exists('scopeCode', $body),
        );
        $this->provider->assertSectionAllowed($field, $user);
        $this->assertScopeWritable($scope, $user);

        $oldValue = $this->provider->toSettingDto($field, $scope, $this->provider->ownPaths($scope))->value;

        if ($operation instanceof DeleteOperationInterface) {
            if ($scope['scope'] === ConfigSetting::SCOPE_DEFAULT) {
                throw new BadRequestHttpException('The default scope has no parent scope to inherit from. Name a scope and scopeCode in the query string.');
            }
            $service->delete($field['path'], $scope['scope'], $scope['scopeId']);
            $this->logChange('delete', $field, $scope, $oldValue, null);
            return null;
        }

        if (!array_key_exists('value', $body)) {
            throw ValidationException::requiredField('value');
        }
        $value = $this->normalizeValue($body['value']);
        $service->save($field['path'], $value, $scope['scope'], $scope['scopeId']);
        $this->logChange('update', $field, $scope, $oldValue, $value);

        return $this->provider->toSettingDto($field, $scope, $this->provider->ownPaths($scope), withOptions: true);
    }

    /**
     * A sensitive value never reaches the activity log.
     *
     * @param FieldInfo $field
     * @param ScopeInfo $scope
     */
    private function logChange(string $action, array $field, array $scope, ?string $oldValue, ?string $newValue): void
    {
        $sensitive = $this->provider->service()->isSensitive($field);
        $entry = [
            'path' => $field['path'],
            'scope' => $scope['scope'],
            'scope_id' => $scope['scopeId'],
        ];
        $oldData = $entry + ['value' => $sensitive ? '******' : $oldValue];
        $newData = $action === 'delete' ? null : new DataObject($entry + ['value' => $sensitive ? '******' : $newValue]);

        $this->logApiActivity('config_setting', $action, $oldData, $newData);
    }

    /**
     * A token with a store restriction writes only its own store views.
     *
     * @param ScopeInfo $scope
     */
    private function assertScopeWritable(array $scope, ApiUser $user): void
    {
        if ($scope['scope'] === ConfigSetting::SCOPE_STORES) {
            $this->assertRequestedStoreAllowed($scope['scopeId'], $user);
        } else {
            $this->assertUnrestricted($user);
        }
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!is_scalar($item)) {
                    throw new BadRequestHttpException('value must be a string, a number, a boolean or a list of them');
                }
            }
            return implode(',', array_map(strval(...), $value));
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new BadRequestHttpException('value must be a string, a number, a boolean or a list of them');
    }
}
