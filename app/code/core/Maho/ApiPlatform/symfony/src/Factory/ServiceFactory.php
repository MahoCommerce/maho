<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Factory;

use Maho\ApiPlatform\Service\ProductService;
use Maho\ApiPlatform\Service\CustomerService;
use Maho\ApiPlatform\Service\CartService;
use Maho\ApiPlatform\Service\OrderService;
use Maho\ApiPlatform\Service\PaymentService;

/**
 * Service Factory - Creates service instances with proper dependencies
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
                $meilisearchClient = new \Meilisearch\Client(
                    \Mage::getStoreConfig('maho_api/meilisearch/host'),
                    \Mage::getStoreConfig('maho_api/meilisearch/api_key'),
                );
            } catch (\Exception $e) {
                \Mage::log('Meilisearch initialization failed: ' . $e->getMessage());
            }
        }

        $indexPrefix = \Mage::getStoreConfig('maho_api/meilisearch/index_prefix') ?: 'maho_';

        return new ProductService($meilisearchClient, $indexPrefix);
    }

    /**
     * Create CustomerService
     */
    public function createCustomerService(): CustomerService
    {
        return new CustomerService();
    }

    /**
     * Create CartService
     */
    public function createCartService(): CartService
    {
        return new CartService();
    }

    /**
     * Create OrderService
     */
    public function createOrderService(): OrderService
    {
        return new OrderService();
    }

    /**
     * Create PaymentService
     */
    public function createPaymentService(): PaymentService
    {
        return new PaymentService();
    }
}
