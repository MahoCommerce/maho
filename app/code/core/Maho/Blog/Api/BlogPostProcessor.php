<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Blog\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;

final class BlogPostProcessor extends CrudProcessor
{
    protected ?string $writePermission = 'blog-posts/write';
    protected ?string $deletePermission = 'blog-posts/delete';

    #[\Override]
    protected function getEntityStoreIds(object $model): array
    {
        return $model->getStores();
    }

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        if ($data instanceof BlogPost) {
            $storeIds = $this->resolveStoreIds($data->stores, $user);
            $model->setData('stores', $storeIds);
        }
    }
}
