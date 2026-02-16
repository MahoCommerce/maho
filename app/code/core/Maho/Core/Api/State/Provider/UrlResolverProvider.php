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

namespace Maho\Core\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\Core\Api\Resource\UrlResolveResult;
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

        // Try without .html suffix (rewrites may be stored without it)
        if (str_ends_with($path, '.html')) {
            $pathWithoutHtml = substr($path, 0, -5);
            $rewrite->loadByRequestPath($pathWithoutHtml);
            /** @phpstan-ignore if.alwaysFalse */
            if ($rewrite->getId()) {
                return $this->processRewrite($rewrite, $result);
            }
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

        // Check for blog post by URL key
        $blogPost = $this->findBlogPostByUrlKey($path, $storeId);
        if ($blogPost) {
            $result->type = 'blog_post';
            $result->id = (int) $blogPost->getId();
            $result->identifier = $blogPost->getUrlKey();
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
            $productId = (int) $matches[1];

            // Check if this is a simple product with a configurable parent
            $productId = $this->resolveParentProductId($productId);

            $result->type = 'product';
            $result->id = $productId;
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

        /** @var \Mage_Catalog_Model_Product $product */
        $product = $collection->getFirstItem();

        if (!$product->getId()) {
            return null;
        }

        // If this is a simple product, check if it has a parent (configurable, grouped, or bundle)
        // and return the parent instead (for proper display)
        if ($product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            $parent = $this->findParentProduct($product->getId());
            if ($parent) {
                return $parent;
            }
        }

        return $product;
    }

    /**
     * If product is a simple with a parent (configurable, grouped, or bundle), return the parent ID
     */
    private function resolveParentProductId(int $productId): int
    {
        $product = \Mage::getModel('catalog/product')->load($productId);

        if ($product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            $parent = $this->findParentProduct($productId);
            if ($parent) {
                return (int) $parent->getId();
            }
        }

        return $productId;
    }

    /**
     * Find parent product (configurable, grouped, or bundle) for a simple product
     */
    private function findParentProduct(int $childId): ?\Mage_Catalog_Model_Product
    {
        // Check configurable parent
        $parentIds = \Mage::getModel('catalog/product_type_configurable')
            ->getParentIdsByChild($childId);

        // Check grouped parent
        if (empty($parentIds)) {
            $parentIds = \Mage::getModel('catalog/product_type_grouped')
                ->getParentIdsByChild($childId);
        }

        // Check bundle parent
        if (empty($parentIds)) {
            $parentIds = \Mage::getModel('bundle/product_type')
                ->getParentIdsByChild($childId);
        }

        if (!empty($parentIds)) {
            $parentId = reset($parentIds);
            $parent = \Mage::getModel('catalog/product')->load($parentId);

            if ($parent->getId()
                && $parent->getStatus() == \Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            ) {
                return $parent;
            }
        }

        return null;
    }

    private function findBlogPostByUrlKey(string $urlKey, int $storeId): ?\Mage_Core_Model_Abstract
    {
        // Check if blog module exists
        if (!\Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            return null;
        }

        try {
            $collection = \Mage::getModel('blog/post')->getCollection();
            if (!$collection) {
                return null;
            }
            $collection->addFieldToFilter('url_key', $urlKey);
            $collection->addFieldToFilter('is_active', 1);
            $collection->addStoreFilter($storeId); /** @phpstan-ignore method.notFound */
            $collection->setPageSize(1);

            /** @var \Mage_Core_Model_Abstract $post */
            $post = $collection->getFirstItem();

            return $post->getId() ? $post : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
