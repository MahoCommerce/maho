<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\UrlResolveResult;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * URL Resolver State Provider
 *
 * Resolves URL paths to their targets (CMS pages, categories, products)
 *
 * @implements ProviderInterface<UrlResolveResult>
 */
final class UrlResolverProvider implements ProviderInterface
{
    /**
     * @return UrlResolveResult|UrlResolveResult[]
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UrlResolveResult|array
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName();

        // Handle GraphQL query
        if ($operationName === 'resolveUrl') {
            $path = $context['args']['path'] ?? '';
        } else {
            // REST endpoint - path comes from query parameter
            $path = $context['filters']['path'] ?? '';
        }

        // Clean the path
        $path = trim($path, '/');

        // Empty path = homepage
        if (empty($path)) {
            $path = 'home';
        }

        $result = $this->resolveUrl($path);

        // For GetCollection, return as array (empty if not found)
        if ($operation instanceof \ApiPlatform\Metadata\CollectionOperationInterface) {
            // Return empty array for not_found to avoid IRI generation issues
            if ($result->type === 'not_found') {
                return [];
            }
            return [$result];
        }

        return $result;
    }

    private function resolveUrl(string $path): UrlResolveResult
    {
        $result = new UrlResolveResult();
        $storeId = StoreContext::getStoreId();

        // First, check URL rewrites table
        $rewrite = \Mage::getModel('core/url_rewrite');
        $rewrite->setStoreId($storeId);
        $rewrite->loadByRequestPath($path);

        if ($rewrite->getId()) {
            return $this->processRewrite($rewrite, $result);
        }

        // Try with .html suffix (common in Magento)
        $rewrite->loadByRequestPath($path . '.html');
        /** @phpstan-ignore if.alwaysFalse */
        if ($rewrite->getId()) {
            return $this->processRewrite($rewrite, $result);
        }

        // Check for CMS page by identifier directly
        $cmsPage = \Mage::getModel('cms/page');
        $pageId = $cmsPage->checkIdentifier($path, $storeId);

        // Also try with .html suffix (common in Maho/Magento)
        if (!$pageId) {
            $pageId = $cmsPage->checkIdentifier($path . '.html', $storeId);
        }

        if ($pageId) {
            $cmsPage->load($pageId);
            if ($cmsPage->getIsActive()) {
                $result->type = 'cms_page';
                $result->id = (int) $cmsPage->getId();
                $result->identifier = $cmsPage->getIdentifier();
                return $result;
            }
        }

        // Check for category by URL key
        $category = $this->findCategoryByUrlKey($path, $storeId);
        if ($category) {
            $result->type = 'category';
            $result->id = (int) $category->getId();
            $result->identifier = $category->getUrlKey();
            return $result;
        }

        // Check for product by URL key
        $product = $this->findProductByUrlKey($path, $storeId);
        if ($product) {
            $result->type = 'product';
            $result->id = (int) $product->getId();
            $result->identifier = $product->getUrlKey();
            return $result;
        }

        // Not found
        $result->type = 'not_found';
        return $result;
    }

    private function processRewrite(\Mage_Core_Model_Url_Rewrite $rewrite, UrlResolveResult $result): UrlResolveResult
    {
        $targetPath = $rewrite->getTargetPath();
        $options = $rewrite->getOptions();

        // Check for redirect
        if ($options === 'RP' || $options === 'R') {
            $result->type = 'redirect';
            $result->redirectUrl = $targetPath;
            $result->redirectType = 301;
            return $result;
        }

        // Parse the target path to determine entity type
        // Format: catalog/product/view/id/123 or catalog/category/view/id/456 or cms/page/view/page_id/789

        // Use request_path as identifier to avoid extra DB load
        // The request_path is the URL key (possibly with .html suffix)
        $identifier = $this->extractIdentifierFromPath($rewrite->getRequestPath());

        if (preg_match('#^catalog/product/view/id/(\d+)#', $targetPath, $matches)) {
            $result->type = 'product';
            $result->id = (int) $matches[1];
            $result->identifier = $identifier;
            return $result;
        }

        if (preg_match('#^catalog/category/view/id/(\d+)#', $targetPath, $matches)) {
            $result->type = 'category';
            $result->id = (int) $matches[1];
            $result->identifier = $identifier;
            return $result;
        }

        if (preg_match('#^cms/page/view/page_id/(\d+)#', $targetPath, $matches)) {
            $result->type = 'cms_page';
            $result->id = (int) $matches[1];
            $result->identifier = $identifier;
            return $result;
        }

        // Custom rewrite - return as not_found with redirect info
        $result->type = 'not_found';
        return $result;
    }

    /**
     * Extract URL key/identifier from request path
     * Removes .html suffix and any category path prefix
     */
    private function extractIdentifierFromPath(string $requestPath): string
    {
        // Remove .html suffix
        $identifier = preg_replace('/\.html$/', '', $requestPath);

        // If path contains slashes (category/product format), take the last segment
        if (str_contains($identifier, '/')) {
            $segments = explode('/', $identifier);
            $identifier = end($segments);
        }

        return $identifier;
    }

    private function findCategoryByUrlKey(string $urlKey, int $storeId): ?\Mage_Catalog_Model_Category
    {
        $collection = \Mage::getModel('catalog/category')->getCollection();
        $collection->setStoreId($storeId);
        $collection->addAttributeToFilter('url_key', $urlKey);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->setPageSize(1);

        $category = $collection->getFirstItem();

        return $category->getId() ? $category : null;
    }

    private function findProductByUrlKey(string $urlKey, int $storeId): ?\Mage_Catalog_Model_Product
    {
        $collection = \Mage::getModel('catalog/product')->getCollection();
        $collection->setStoreId($storeId);
        $collection->addAttributeToFilter('url_key', $urlKey);
        $collection->addAttributeToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        $collection->setPageSize(1);

        $product = $collection->getFirstItem();

        /** @phpstan-ignore return.type */
        return $product->getId() ? $product : null;
    }
}
