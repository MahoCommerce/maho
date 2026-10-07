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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @phpstan-import-type FieldInfo from ConfigSettingProvider
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

        $path = (string) ($uriVariables['path'] ?? $params['path'] ?? '');
        $field = $this->provider->field($path);
        if ($field === null) {
            throw new ValidationException("'$path' is not a system configuration field", 'path', 'Invalid');
        }

        $scope = $this->provider->resolveScope(
            isset($params['scope']) ? (string) $params['scope'] : ConfigSetting::SCOPE_DEFAULT,
            isset($params['scopeCode']) ? (string) $params['scopeCode'] : null,
        );
        if (!$this->provider->isShownAtScope($field, $scope['scope'])) {
            throw new ValidationException(
                sprintf("Field '%s' cannot be set at scope '%s'", $path, $scope['scope']),
                'scope',
                'Invalid',
            );
        }
        $this->provider->assertSectionAllowed($field, $user);
        $this->assertScopeWritable($scope, $user);

        $oldValue = $this->provider->toSettingDto($field, $scope, $this->provider->ownPaths($scope))->value;

        if ($operation instanceof DeleteOperationInterface) {
            $this->restoreInheritance($field, $scope, $oldValue, $user);
            return null;
        }

        if (!array_key_exists('value', $body)) {
            throw ValidationException::requiredField('value');
        }
        $value = $this->normalizeValue($body['value']);
        $this->write($field, $scope, $value, $oldValue, $user);

        return $this->provider->toSettingDto($field, $scope, $this->provider->ownPaths($scope), withOptions: true);
    }

    /**
     * @param FieldInfo $field
     * @param ScopeInfo $scope
     */
    private function write(array $field, array $scope, ?string $value, ?string $oldValue, ApiUser $user): void
    {
        try {
            \Mage::getModel('adminhtml/config_data')
                ->setSection($field['section'])
                ->setWebsite($scope['scope'] === ConfigSetting::SCOPE_WEBSITES ? $scope['scopeCode'] : '')
                ->setStore($scope['scope'] === ConfigSetting::SCOPE_STORES ? $scope['scopeCode'] : '')
                ->setGroups([$field['group'] => ['fields' => [$field['field'] => ['value' => $value]]]])
                ->save();
        } catch (\Mage_Core_Exception $e) {
            throw new ValidationException($e->getMessage(), 'value', 'Invalid', previous: $e);
        }

        $this->afterChange($field, $scope);
        $this->logChange('update', $field, $scope, $oldValue, $value, $user);
    }

    /**
     * @param FieldInfo $field
     * @param ScopeInfo $scope
     */
    private function restoreInheritance(array $field, array $scope, ?string $oldValue, ApiUser $user): void
    {
        if ($scope['scope'] === ConfigSetting::SCOPE_DEFAULT) {
            throw new ValidationException('The default scope has no parent scope to inherit from', 'scope', 'Invalid');
        }

        \Mage::getConfig()->deleteConfig($field['path'], $scope['scope'], $scope['scopeId']);

        $this->afterChange($field, $scope);
        $this->logChange('delete', $field, $scope, $oldValue, null, $user);
    }

    /**
     * Mirrors Mage_Adminhtml_System_ConfigController::saveAction() after the save.
     *
     * @param FieldInfo $field
     * @param ScopeInfo $scope
     */
    private function afterChange(array $field, array $scope): void
    {
        $website = $scope['scope'] === ConfigSetting::SCOPE_WEBSITES ? $scope['scopeCode'] : null;
        $store = $scope['scope'] === ConfigSetting::SCOPE_STORES ? $scope['scopeCode'] : null;

        \Mage::getConfig()->reinit();
        \Mage::dispatchEvent('admin_system_config_section_save_after', [
            'website' => $website,
            'store' => $store,
            'section' => $field['section'],
        ]);
        \Mage::app()->reinitStores();
        \Mage::dispatchEvent(
            "admin_system_config_changed_section_{$field['section']}",
            ['website' => $website, 'store' => $store],
        );
    }

    /**
     * A sensitive value never reaches the activity log.
     *
     * @param FieldInfo $field
     * @param ScopeInfo $scope
     */
    private function logChange(string $action, array $field, array $scope, ?string $oldValue, ?string $newValue, ApiUser $user): void
    {
        $sensitive = $this->provider->isSensitive($field);
        $entry = [
            'path' => $field['path'],
            'scope' => $scope['scope'],
            'scope_id' => $scope['scopeId'],
        ];
        $oldData = $entry + ['value' => $sensitive ? '******' : $oldValue];
        $newData = $action === 'delete' ? null : new DataObject($entry + ['value' => $sensitive ? '******' : $newValue]);

        $this->logApiActivity('config_setting', $action, $oldData, $newData, $user);
    }

    /**
     * A token with a store restriction writes only its own store views.
     *
     * @param ScopeInfo $scope
     */
    private function assertScopeWritable(array $scope, ApiUser $user): void
    {
        if ($user->getAllowedStoreIds() === null) {
            return;
        }
        if ($scope['scope'] !== ConfigSetting::SCOPE_STORES || !$user->canAccessStore($scope['scopeId'])) {
            throw new AccessDeniedHttpException('Token is not authorized for the requested scope.');
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
                    throw new ValidationException('value must be a string, a number, a boolean or a list of them', 'value', 'Type');
                }
            }
            return implode(',', array_map(strval(...), $value));
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new ValidationException('value must be a string, a number, a boolean or a list of them', 'value', 'Type');
    }
}
