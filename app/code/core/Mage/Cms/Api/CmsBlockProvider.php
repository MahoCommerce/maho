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

use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * CMS Block Provider — extends CrudProvider with block-specific filters and named queries.
 *
 * All field mapping and DTO construction is handled by CrudResource/CrudProvider.
 * This class only adds collection filters and the cmsBlockByIdentifier query.
 */
final class CmsBlockProvider extends CrudProvider
{
    protected int $defaultPageSize = 100;
    protected int $maxPageSize = 100;
    protected array $defaultSort = ['title' => 'ASC'];

    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'cmsBlockByIdentifier') {
            $identifier = $context['args']['identifier'] ?? null;
            if (!$identifier) {
                return null;
            }

            $collection = \Mage::getModel('cms/block')->getCollection();
            $collection->addStoreFilter(StoreContext::getStoreId());
            $collection->addFieldToFilter('identifier', $identifier);
            $collection->addFieldToFilter('is_active', 1);
            $collection->setPageSize(1);

            $block = $collection->getFirstItem();

            return $block->getId() ? $this->toDto($block) : null;
        }
        return null;
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $collection->addFieldToFilter('is_active', 1);

        if (!empty($filters['identifier'])) {
            $collection->addFieldToFilter('identifier', ['like' => '%' . $filters['identifier'] . '%']);
        }

        $search = $filters['search'] ?? $filters['q'] ?? null;
        if ($search) {
            $collection->addFieldToFilter(
                ['title', 'content', 'identifier'],
                [
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                ],
            );
        }
    }
}
