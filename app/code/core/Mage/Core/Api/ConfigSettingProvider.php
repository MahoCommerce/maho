<?php

/**
 * Reads the system.xml fields and their values at one scope.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @phpstan-import-type FieldInfo from \Mage_Core_Service_Config
 * @phpstan-type ScopeInfo array{scope: string, scopeId: int, scopeCode: ?string}
 */
final class ConfigSettingProvider extends \Maho\ApiPlatform\Provider
{
    private const MAX_OPTIONS = 50;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $user = $this->requireUser();
        $filters = $this->requestFilters($context);
        $scope = $this->resolveScope(
            isset($filters['scope']) ? (string) $filters['scope'] : ConfigSetting::SCOPE_DEFAULT,
            isset($filters['scopeCode']) ? (string) $filters['scopeCode'] : null,
        );
        $this->assertScopeReadable($scope, $user);

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideSettings($filters, $scope, $user, $context);
        }

        $field = $this->service()->getField((string) ($uriVariables['path'] ?? $filters['path'] ?? ''));
        $this->assertSectionAllowed($field, $user);

        return $this->toSettingDto($field, $scope, $this->ownPaths($scope), withOptions: true);
    }

    /**
     * Query string, GraphQL args and MCP arguments all carry the same keys.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function requestFilters(array $context): array
    {
        $query = $context['request']?->query->all() ?? [];
        return ($context['args'] ?? []) + ($context['filters'] ?? []) + $query;
    }

    /**
     * @param array<string, mixed> $filters
     * @param ScopeInfo $scope
     * @return TraversablePaginator<ConfigSetting>
     */
    private function provideSettings(array $filters, array $scope, ApiUser $user, array $context): TraversablePaginator
    {
        $pathPrefix = isset($filters['pathPrefix']) ? ltrim((string) $filters['pathPrefix'], '/') : '';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $matching = [];
        foreach ($this->service()->getFields() as $field) {
            if (!$this->service()->isShownAtScope($field, $scope['scope'])) {
                continue;
            }
            if ($pathPrefix !== '' && !str_starts_with($field['path'], $pathPrefix)) {
                continue;
            }
            if ($search !== ''
                && stripos($field['path'], $search) === false
                && stripos($field['label'], $search) === false
                && stripos($field['comment'], $search) === false
            ) {
                continue;
            }
            if ($this->deniedSection($field, $user) !== null) {
                continue;
            }
            $matching[] = $field;
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(
            $context,
            $this->defaultPageSize,
            $this->maxPageSize,
        );

        $ownPaths = $this->ownPaths($scope);
        $items = [];
        foreach (array_slice($matching, ($page - 1) * $pageSize, $pageSize) as $field) {
            $items[] = $this->toSettingDto($field, $scope, $ownPaths);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, count($matching));
    }

    /**
     * @param FieldInfo $field
     * @param ScopeInfo $scope
     * @param array<string, true> $ownPaths Paths that have a row at this scope
     */
    public function toSettingDto(array $field, array $scope, array $ownPaths, bool $withOptions = false): ConfigSetting
    {
        $dto = new ConfigSetting();
        $dto->path = $field['path'];
        $dto->scope = $scope['scope'];
        $dto->scopeCode = $scope['scopeCode'];
        $dto->label = $field['label'];
        $dto->groupLabel = $field['groupLabel'];
        $dto->sectionLabel = $field['sectionLabel'];
        $dto->comment = $field['comment'] !== '' ? $field['comment'] : null;
        $dto->frontendType = $field['frontendType'];
        $dto->isSensitive = $this->service()->isSensitive($field);
        $dto->inherited = $scope['scope'] !== ConfigSetting::SCOPE_DEFAULT && !isset($ownPaths[$field['path']]);
        $dto->value = $dto->isSensitive ? null : $this->valueAtScope($field['path'], $scope);
        $dto->options = $withOptions ? $this->options($field) : null;

        return $dto;
    }

    /**
     * @param ScopeInfo $scope
     */
    private function valueAtScope(string $path, array $scope): ?string
    {
        $value = match ($scope['scope']) {
            ConfigSetting::SCOPE_WEBSITES => \Mage::app()->getWebsite($scope['scopeId'])->getConfig($path),
            ConfigSetting::SCOPE_STORES => \Mage::getStoreConfig($path, $scope['scopeId']),
            default => \Mage::getConfig()->getNode('default/' . $path),
        };

        if ($value instanceof \Maho\Simplexml\Element) {
            $value = $value->hasChildren() ? null : (string) $value;
        }
        if ($value === false || $value === null || is_array($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Paths that have their own core_config_data row at the scope.
     *
     * @param ScopeInfo $scope
     * @return array<string, true>
     */
    public function ownPaths(array $scope): array
    {
        if ($scope['scope'] === ConfigSetting::SCOPE_DEFAULT) {
            return [];
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $select = $read->select()
            ->from($resource->getTableName('core/config_data'), ['path'])
            ->where('scope = ?', $scope['scope'])
            ->where('scope_id = ?', $scope['scopeId']);

        return array_fill_keys($read->fetchCol($select), true);
    }

    /**
     * Return the scope that scope and scopeCode name.
     * A wrong value is a 422 when it comes from the body and a 400 when it comes from the query string.
     *
     * @return ScopeInfo
     */
    public function resolveScope(string $scope, ?string $scopeCode, bool $inBody = false): array
    {
        if ($scope === ConfigSetting::SCOPE_DEFAULT) {
            return ['scope' => $scope, 'scopeId' => 0, 'scopeCode' => null];
        }

        if ($scopeCode === null || $scopeCode === '') {
            throw self::scopeError("scopeCode is required for scope '$scope'", 'scopeCode', 'NotBlank', $inBody);
        }

        if ($scope === ConfigSetting::SCOPE_WEBSITES) {
            try {
                $website = \Mage::app()->getWebsite($scopeCode);
            } catch (\Mage_Core_Exception) {
                $website = null;
            }
            if (!$website || !$website->getId()) {
                throw self::scopeError("Website '$scopeCode' not found", 'scopeCode', 'Invalid', $inBody);
            }
            return ['scope' => $scope, 'scopeId' => (int) $website->getId(), 'scopeCode' => $website->getCode()];
        }

        if ($scope === ConfigSetting::SCOPE_STORES) {
            try {
                $store = \Mage::app()->getStore($scopeCode);
            } catch (\Mage_Core_Model_Store_Exception) {
                $store = null;
            }
            if (!$store || !$store->getId()) {
                throw self::scopeError("Store view '$scopeCode' not found", 'scopeCode', 'Invalid', $inBody);
            }
            return ['scope' => $scope, 'scopeId' => (int) $store->getId(), 'scopeCode' => $store->getCode()];
        }

        throw self::scopeError('scope must be default, websites or stores', 'scope', 'Choice', $inBody);
    }

    private static function scopeError(string $message, string $field, string $constraint, bool $inBody): \Exception
    {
        return $inBody ? new ValidationException($message, $field, $constraint) : new BadRequestHttpException($message);
    }

    /**
     * A token with a store restriction reads only the store views it may access.
     *
     * @param ScopeInfo $scope
     */
    private function assertScopeReadable(array $scope, ApiUser $user): void
    {
        if ($scope['scope'] === ConfigSetting::SCOPE_STORES && !$user->canAccessStore($scope['scopeId'])) {
            throw new AccessDeniedHttpException('Token is not authorized for the requested store.');
        }
    }

    /**
     * An admin token sees the sections that its admin role allows. A service token sees all.
     */
    public function isSectionAllowed(string $section, ApiUser $user): bool
    {
        if (!$user->isAdmin()) {
            return true;
        }

        try {
            return (bool) \Mage::getSingleton('admin/session')->isAllowed('admin/system/config/' . $section);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * A field that stores its value in another section needs access to the two sections, as in the admin.
     *
     * @param FieldInfo $field
     */
    public function assertSectionAllowed(array $field, ApiUser $user): void
    {
        $section = $this->deniedSection($field, $user);
        if ($section !== null) {
            throw new AccessDeniedHttpException(
                sprintf('Your admin role does not grant access to the configuration section "%s".', $section),
            );
        }
    }

    /**
     * @param FieldInfo $field
     * @return string|null the first section of $field that the admin role of $user does not allow
     */
    private function deniedSection(array $field, ApiUser $user): ?string
    {
        return array_find(
            $this->service()->getAclSections($field),
            fn(string $section): bool => !$this->isSectionAllowed($section, $user),
        );
    }

    public function service(): \Mage_Core_Service_Config
    {
        /** @var \Mage_Core_Service_Config */
        return \Mage::getService('core/config');
    }

    /**
     * The values of a select, multiselect or boolean field with their labels, as System >
     * Configuration shows them. Null when the field has no source model or more than MAX_OPTIONS values.
     *
     * @param FieldInfo $field
     * @return list<array{value: string, label: string}>|null
     */
    public function options(array $field): ?array
    {
        if ($field['frontendType'] === 'boolean') {
            return [
                ['value' => '1', 'label' => \Mage::helper('core')->__('Yes')],
                ['value' => '0', 'label' => \Mage::helper('core')->__('No')],
            ];
        }
        if ($field['sourceModel'] === null || !in_array($field['frontendType'], ['select', 'multiselect'], true)) {
            return null;
        }
        $factoryName = $field['sourceModel'];
        $method = null;
        if (preg_match('/^([^:]+?)::([^:]+?)$/', $factoryName, $matches)) {
            [, $factoryName, $method] = $matches;
        }
        $multiselect = $field['frontendType'] === 'multiselect';
        try {
            $source = \Mage::getSingleton($factoryName);
            if (!is_object($source)) {
                return null;
            }
            if ($source instanceof \Maho\DataObject) {
                $source->setPath($field['path']);
            }
            if ($method === null) {
                $raw = method_exists($source, 'toOptionArray') ? $source->toOptionArray($multiselect) : [];
            } else {
                $raw = $source->$method() ?? [];
                if (!$multiselect) {
                    $raw = array_map(static fn($value, $label): array => ['value' => $value, 'label' => $label], array_keys($raw), $raw);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        $options = [];
        $this->flattenOptions(is_array($raw) ? $raw : [], $options);

        return $options === [] || count($options) > self::MAX_OPTIONS ? null : $options;
    }

    /**
     * Add the options in $raw to $options. An option group holds its options in its value.
     *
     * @param array<mixed> $raw
     * @param list<array{value: string, label: string}> $options
     */
    private function flattenOptions(array $raw, array &$options): void
    {
        foreach ($raw as $option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }
            if (is_array($option['value'])) {
                $this->flattenOptions($option['value'], $options);
                continue;
            }
            if (is_scalar($option['value'])) {
                $options[] = ['value' => (string) $option['value'], 'label' => (string) ($option['label'] ?? $option['value'])];
            }
        }
    }
}
