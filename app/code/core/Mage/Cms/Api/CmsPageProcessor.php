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

final class CmsPageProcessor extends CrudProcessor
{
    protected ?string $writePermission = 'cms-pages/write';
    protected ?string $deletePermission = 'cms-pages/delete';

    #[\Override]
    protected function getEntityStoreIds(object $model): ?array
    {
        $stores = $model->getStoreId();
        return is_array($stores) ? $stores : [$stores];
    }

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        $content = $model->getData('content');
        if ($content !== null) {
            $model->setData('content', \Mage::getSingleton('core/input_filter_maliciousCode')->filter($content));
        }

        if ($data instanceof CmsPage) {
            $storeIds = $this->resolveStoreIds($data->stores, $user);
            $model->setData('stores', $storeIds);
        }
    }
}
