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

namespace Maho\ApiPlatform\Factory;

use Mage\Catalog\Api\ProductService;

/**
 * Service Factory
 *
 * Factory methods for services that need special construction logic
 * (e.g. optional external dependencies that can't be autowired).
 */
class ServiceFactory
{
    /**
     * Create ProductService with Meilisearch client if configured
     */
    public function createProductService(): ProductService
    {
        $meilisearchClient = null;

        if (\Mage::getStoreConfigFlag('maho_api/meilisearch/enabled')) {
            try {
                $clientClass = 'Meilisearch\\Client';
                if (class_exists($clientClass)) {
                    $meilisearchClient = new $clientClass(
                        \Mage::getStoreConfig('maho_api/meilisearch/host'),
                        \Mage::getStoreConfig('maho_api/meilisearch/api_key'),
                    );
                }
            } catch (\Exception $e) {
                \Mage::log('Meilisearch initialization failed: ' . $e->getMessage());
            }
        }

        $indexPrefix = \Mage::getStoreConfig('maho_api/meilisearch/index_prefix') ?: 'maho_';

        return new ProductService($meilisearchClient, $indexPrefix);
    }
}
