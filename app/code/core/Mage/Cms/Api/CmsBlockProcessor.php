<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Cms\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;

/**
 * CMS Block Processor — extends CrudProcessor with content sanitization and store access checks.
 *
 * All field mapping and CRUD routing is handled by CrudResource/CrudProcessor.
 * This class only adds content sanitization and store-level authorization.
 */
final class CmsBlockProcessor extends CrudProcessor
{
    protected ?string $writePermission = 'cms-blocks/write';
    protected ?string $deletePermission = 'cms-blocks/delete';

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        $content = $model->getData('content');
        if ($content !== null) {
            $model->setData('content', \Mage::getSingleton('core/input_filter_maliciousCode')->filter($content));
        }

        if ($data instanceof CmsBlock) {
            $storeIds = $this->resolveStoreIds($data->stores, $user);
            $model->setData('stores', $storeIds);
        }
    }

    #[\Override]
    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'CMS block not found');

        $blockStores = $model->getStoreId();
        $this->validateEntityStoreAccess(is_array($blockStores) ? $blockStores : [$blockStores], $user, 'block');

        return parent::processUpdate($id, $data, $user);
    }

    #[\Override]
    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'CMS block not found');

        $blockStores = $model->getStoreId();
        $this->validateEntityStoreAccess(is_array($blockStores) ? $blockStores : [$blockStores], $user, 'block');

        return parent::processDelete($id, $user);
    }
}
