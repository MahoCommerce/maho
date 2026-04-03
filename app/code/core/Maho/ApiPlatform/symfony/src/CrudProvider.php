<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Convention-based provider for CrudResource subclasses.
 *
 * Leverages the parent Provider's provideCollection() and pagination — no duplication.
 * Only overrides toDto() for auto-mapping and applyCollectionFilters() for store/EAV handling.
 *
 * Hooks for subclasses:
 *   - applyCollectionFilters($collection, $filters) — add WHERE clauses (call parent first)
 *   - afterMap($dto, $model) — enrich DTO with computed/related data
 */
class CrudProvider extends Provider
{
    /** @var class-string<CrudResource>|null */
    protected ?string $resourceClass = null;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        StoreContext::ensureStore();

        $this->resourceClass = $operation->getClass();

        // Derive modelAlias from CrudResource metadata
        if (is_subclass_of($this->resourceClass, CrudResource::class)) {
            $this->modelAlias = $this->resourceClass::metadata()->model;
        }

        // Delegate to parent — handles named operations, collection, single item
        return parent::provide($operation, $uriVariables, $context);
    }

    /**
     * Single item with store availability check.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?Resource
    {
        $model = \Mage::getModel($this->modelAlias)->load($id);
        if (!$model->getId()) {
            return null;
        }

        // Store scoping: check if model is available for current store
        if (method_exists($model->getResource(), 'lookupStoreIds')) {
            $storeIds = $model->getResource()->lookupStoreIds($model->getId());
            if (!StoreContext::isAvailableForStore($storeIds, StoreContext::getStoreId())) {
                return null;
            }
        }

        return $this->toDto($model);
    }

    /**
     * Auto-map model to DTO via CrudResource convention + dispatch extension event.
     * Parent's provideCollection() calls this for each item — no need to override provideCollection().
     */
    #[\Override]
    public function toDto(object $model): Resource
    {
        $dto = $this->resourceClass::fromModel($model);
        $this->afterMap($dto, $model);

        // Dispatch extension event: api_article_dto_build, api_cms_block_dto_build, etc.
        $shortName = (new \ReflectionClass($this->resourceClass))->getShortName();
        $eventName = 'api_' . strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($shortName))) . '_dto_build';
        \Mage::dispatchEvent($eventName, ['model' => $model, 'dto' => $dto]);

        return $dto;
    }

    /**
     * Auto-apply store filtering and EAV attribute loading.
     * Subclasses should call parent::applyCollectionFilters() first, then add their own filters.
     */
    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        $storeId = StoreContext::getStoreId();

        // Store filtering — auto-detect the collection's method
        if (method_exists($collection, 'addStoreFilter')) {
            $collection->addStoreFilter($storeId);
        } elseif (method_exists($collection, 'setStoreId')) {
            $collection->setStoreId($storeId);
        }

        // EAV collections need explicit attribute selection — only load what the DTO needs
        if ($collection instanceof \Mage_Eav_Model_Entity_Collection_Abstract
            && $this->resourceClass
            && is_subclass_of($this->resourceClass, CrudResource::class)
        ) {
            foreach ($this->resourceClass::metadata()->fields as $field) {
                if (!$field->isIdentifier) {
                    try {
                        $collection->addAttributeToSelect($field->modelField);
                    } catch (\Throwable) {
                        // Not every DTO field is an EAV attribute — skip silently
                    }
                }
            }
        }
    }

    /**
     * Hook: enrich the DTO after auto-mapping.
     * Use this for computed fields, related data, content processing, etc.
     */
    protected function afterMap(Resource $dto, object $model): void {}
}
