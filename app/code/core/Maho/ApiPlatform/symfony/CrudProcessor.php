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
use Maho\ApiPlatform\Security\ApiUser;

/**
 * Convention-based processor for CrudResource subclasses.
 *
 * Leverages parent Processor's routing (create/update/delete) and persistence.
 * Only overrides applyData() for auto-mapping and buildResponse() for auto-DTO.
 *
 * Hooks for subclasses:
 *   - validate($data, $model, $isNew) — throw on invalid data
 *   - beforeSave($model, $data, $user) — pre-save adjustments
 *   - afterSave($model, $data, $user) — post-save actions (reindex, cache, etc.)
 */
class CrudProcessor extends Processor
{
    /** @var class-string<CrudResource>|null */
    protected ?string $resourceClass = null;

    /**
     * Set up modelAlias and entity labels from CrudResource metadata,
     * then delegate entirely to parent's routing.
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->resourceClass = $operation->getClass();

        if (is_subclass_of($this->resourceClass, CrudResource::class)) {
            $meta = $this->resourceClass::metadata();
            $this->modelAlias = $meta->model;

            if (!$this->entityType) {
                $short = (new \ReflectionClass($this->resourceClass))->getShortName();
                $this->entityType = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($short)));
                $this->entityLabel = $short;
            }

            if (!$this->writePermission && $operation instanceof \ApiPlatform\Metadata\HttpOperation) {
                $base = trim(preg_replace('#/\{[^}]+\}#', '', $operation->getUriTemplate() ?? ''), '/');
                $this->writePermission = $base . '/write';
                $this->deletePermission = $base . '/delete';
            }
        }

        // Parent handles auth check, permission check, and create/update/delete routing
        return parent::process($data, $operation, $uriVariables, $context);
    }

    /**
     * Apply DTO data to model via convention-based mapping.
     * Called by parent's processCreate() and processUpdate().
     */
    #[\Override]
    protected function applyData(object $model, mixed $data, ApiUser $user): void
    {
        if ($data instanceof CrudResource) {
            $isNew = !$model->getId();
            $this->validate($data, $model, $isNew);
            $data->applyToModel($model);
            $this->validateStoreAccess($data, $user);
            $this->beforeSave($model, $data, $user);
        }
    }

    /**
     * Return a fresh DTO after save.
     * Called by parent's processCreate() and processUpdate().
     */
    #[\Override]
    protected function buildResponse(object $model, mixed $data): mixed
    {
        // Run afterSave hook
        if ($data instanceof CrudResource) {
            // We don't have $user here, but afterSave is for side-effects (reindex, cache clear)
            $this->afterSave($model, $data);
        }

        if ($this->resourceClass && is_subclass_of($this->resourceClass, CrudResource::class)) {
            // Reload model to get computed/default values set by the DB
            return $this->resourceClass::fromModel(\Mage::getModel($this->modelAlias)->load($model->getId()));
        }

        return $data;
    }

    /**
     * For store-scoped entities (DTO has a `stores` property), validate that
     * the API user can access the entity's assigned stores.
     */
    #[\Override]
    protected function authorizeEntity(object $model, ApiUser $user): void
    {
        if (!$this->isStoreScoped()) {
            return;
        }

        $storeIds = $model->getData('stores') ?? $model->getData('store_id');
        if ($storeIds !== null) {
            $this->validateEntityStoreAccess(
                is_array($storeIds) ? $storeIds : [$storeIds],
                $user,
                $this->entityLabel,
            );
        }
    }

    /**
     * For store-scoped entities, validate the user can access the submitted store IDs.
     */
    private function validateStoreAccess(CrudResource $data, ApiUser $user): void
    {
        if ($this->isStoreScoped()) {
            /** @var array<int> $stores */
            $stores = (new \ReflectionProperty($data, 'stores'))->getValue($data);
            $this->validateEntityStoreAccess($stores, $user, $this->entityLabel);
        }
    }

    private function isStoreScoped(): bool
    {
        return $this->resourceClass !== null && property_exists($this->resourceClass, 'stores');
    }

    /** Hook: validate incoming data before save. Throw BadRequestHttpException on errors. */
    protected function validate(CrudResource $data, object $model, bool $isNew): void {}

    /** Hook: adjust model before save (e.g. set store IDs, computed defaults). */
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void {}

    /** Hook: post-save actions (e.g. reindex, clear cache, send notifications). */
    protected function afterSave(object $model, CrudResource $data): void {}
}
