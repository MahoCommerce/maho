<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Exception;
use Mage;
use Mage_Catalog_Model_Category;
use Mage_Catalog_Model_Product;
use Mage_Cms_Model_Block;
use Mage_Cms_Model_Page;
use Mage_Core_Helper_Data;
use Mage_Downloadable_Model_Link;
use Mage_Review_Model_Review;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sampledata:export',
    description: 'Export current data to sample data format (JSON) - for Maho core developers only',
)]
class SampleDataExport extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Output directory',
            './sample-data-export',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $outputDir = $input->getOption('output');
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                $output->writeln("<error>Failed to create output directory: {$outputDir}</error>");
                return Command::FAILURE;
            }
        }

        $output->writeln('<info>Exporting sample data to JSON...</info>');
        $output->writeln('');

        try {
            $this->exportConfig($outputDir, $output);
            $this->exportAttributeSets($outputDir, $output);
            $this->exportAttributes($outputDir, $output);
            $this->exportCategories($outputDir, $output);
            $this->exportProducts($outputDir, $output);
            $this->exportCms($outputDir, $output);
            $this->exportBlog($outputDir, $output);
            $this->exportReviews($outputDir, $output);
            $this->exportStaticData($outputDir, $output);
            $this->exportProductLinks($outputDir, $output);
            $this->exportTierPrices($outputDir, $output);
            $this->exportCustomOptions($outputDir, $output);
            $this->exportDynamicCategoryRules($outputDir, $output);
        } catch (Exception $e) {
            $output->writeln("<error>Export failed: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln("<info>Export complete! Files saved to: {$outputDir}</info>");

        return Command::SUCCESS;
    }

    private function exportConfig(string $outputDir, OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        $data = [
            'core_config' => [],
        ];

        // Export core_config_data - exclude sensitive paths
        $excludePaths = [
            'web/secure/base_url',
            'web/unsecure/base_url',
            'admin/url/custom',
            'system/smtp%',
            'payment/%',
            'carriers/%',
            'google/%',
            'trans_email/%',
            'contacts/%',
            'sales_email/%',
            'currency/%',
            '%api_key%',
            '%password%',
            '%secret%',
            '%token%',
        ];

        $select = $connection->select()
            ->from($connection->getTableName('core_config_data'), ['scope', 'scope_id', 'path', 'value']);

        foreach ($excludePaths as $excludePath) {
            $select->where('path NOT LIKE ?', $excludePath);
        }

        // Only include sample-data relevant config paths
        $includePaths = [
            'catalog/%',
            'configswatches/%',
            'design/%',
            'cms/%',
        ];

        $orConditions = [];
        foreach ($includePaths as $includePath) {
            $orConditions[] = $connection->quoteInto('path LIKE ?', $includePath);
        }
        $select->where(implode(' OR ', $orConditions));

        $rows = $connection->fetchAll($select);
        foreach ($rows as $row) {
            $value = $row['value'];

            // Convert attribute IDs to codes for configswatches settings
            if (str_starts_with($row['path'], 'configswatches/general/')) {
                $value = $this->convertAttributeIdsToCodesInConfig($row['path'], $value);
            }

            $data['core_config'][] = [
                'scope' => $row['scope'],
                'scope_id' => (int) $row['scope_id'],
                'path' => $row['path'],
                'value' => $value,
            ];
        }

        $this->saveJson($outputDir . '/config.json', $data);
        $output->writeln('  Saved: config.json (' . count($data['core_config']) . ' config entries)');

        // Export permission_block to separate file
        $permissionData = ['permission_block' => []];
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('permission_block')),
        );
        foreach ($rows as $row) {
            $permissionData['permission_block'][] = [
                'block_name' => $row['block_name'],
                'is_allowed' => (int) $row['is_allowed'],
            ];
        }

        $this->saveJson($outputDir . '/permission_block.json', $permissionData);
        $output->writeln('  Saved: permission_block.json (' . count($permissionData['permission_block']) . ' entries)');
    }

    private function exportAttributeSets(string $outputDir, OutputInterface $output): void
    {
        $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();

        /** @var \Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $sets */
        $sets = Mage::getModel('eav/entity_attribute_set')->getCollection()
            ->setEntityTypeFilter($entityTypeId);

        $data = [
            'attribute_sets' => [],
        ];

        foreach ($sets as $set) {
            $setName = $set->getAttributeSetName();

            // Skip the Default set as it's built-in
            if ($setName === 'Default') {
                continue;
            }

            $setData = [
                'name' => $setName,
                'groups' => [],
            ];

            // Get groups for this attribute set
            /** @var \Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection $groups */
            $groups = Mage::getModel('eav/entity_attribute_group')->getCollection()
                ->setAttributeSetFilter($set->getId())
                ->setSortOrder();

            foreach ($groups as $group) {
                $groupData = [
                    'name' => $group->getAttributeGroupName(),
                    'sort_order' => (int) $group->getSortOrder(),
                    'attributes' => [],
                ];

                // Get attributes in this group
                /** @var \Mage_Catalog_Model_Resource_Product_Attribute_Collection $attributes */
                $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
                    ->setAttributeGroupFilter($group->getId())
                    ->addFieldToFilter('is_user_defined', 1);

                foreach ($attributes as $attr) {
                    $groupData['attributes'][] = [
                        'code' => $attr->getAttributeCode(),
                        'sort_order' => (int) $attr->getSortOrder(),
                    ];
                }

                $setData['groups'][] = $groupData;
            }

            $data['attribute_sets'][] = $setData;
        }

        $this->saveJson($outputDir . '/attribute_sets.json', $data);
        $output->writeln('  Saved: attribute_sets.json (' . count($data['attribute_sets']) . ' sets)');
    }

    private function exportAttributes(string $outputDir, OutputInterface $output): void
    {
        /** @var \Mage_Catalog_Model_Resource_Product_Attribute_Collection $attributes */
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter('is_user_defined', 1);

        $data = ['catalog_product' => []];

        foreach ($attributes as $attr) {
            $options = [];
            if ($attr->usesSource()) {
                try {
                    foreach ($attr->getSource()->getAllOptions(false) as $opt) {
                        if (!empty($opt['value']) && !empty($opt['label'])) {
                            $options[] = (string) $opt['label'];
                        }
                    }
                } catch (Exception $e) {
                    // Skip attributes with broken sources
                }
            }

            $config = [
                'type' => $attr->getBackendType(),
                'input' => $attr->getFrontendInput(),
                'label' => $attr->getFrontendLabel(),
                'global' => (int) $attr->getIsGlobal(),
                'visible' => (bool) $attr->getIsVisible(),
                'required' => (bool) $attr->getIsRequired(),
                'user_defined' => true,
                'searchable' => (bool) $attr->getIsSearchable(),
                'filterable' => (bool) $attr->getIsFilterable(),
                'comparable' => (bool) $attr->getIsComparable(),
                'visible_on_front' => (bool) $attr->getIsVisibleOnFront(),
                'used_in_product_listing' => (bool) $attr->getUsedInProductListing(),
                'is_configurable' => (bool) $attr->getIsConfigurable(),
            ];

            if (!empty($options)) {
                $config['option'] = ['values' => $options];
            }

            $data['catalog_product'][$attr->getAttributeCode()] = $config;
        }

        $this->saveJson($outputDir . '/attributes.json', $data);
        $output->writeln('  Saved: attributes.json (' . count($data['catalog_product']) . ' attributes)');
    }

    private function exportCategories(string $outputDir, OutputInterface $output): void
    {
        /** @var Mage_Catalog_Model_Category $rootCategory */
        $rootCategory = Mage::getModel('catalog/category')->load(2); // Default root
        $data = ['categories' => $this->buildCategoryTree($rootCategory)];

        $this->saveJson($outputDir . '/categories.json', $data);
        $count = $this->countCategories($data['categories']);
        $output->writeln("  Saved: categories.json ({$count} categories)");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryTree(Mage_Catalog_Model_Category $parentCategory): array
    {
        $children = $parentCategory->getChildrenCategories();
        $result = [];

        foreach ($children as $category) {
            /** @var Mage_Catalog_Model_Category $category */
            $category = Mage::getModel('catalog/category')->load($category->getId());

            $catData = [
                'name' => $category->getName(),
                'url_key' => $category->getUrlKey(),
                'is_active' => (int) $category->getIsActive(),
                'include_in_menu' => (int) $category->getIncludeInMenu(),
            ];

            if ($category->getDescription()) {
                $catData['description'] = $category->getDescription();
            }
            if ($category->getImage()) {
                $catData['image'] = $category->getImage();
            }

            $subChildren = $this->buildCategoryTree($category);
            if (!empty($subChildren)) {
                $catData['children'] = $subChildren;
            }

            $result[] = $catData;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     */
    private function countCategories(array $categories): int
    {
        $count = count($categories);
        foreach ($categories as $cat) {
            if (!empty($cat['children'])) {
                $count += $this->countCategories($cat['children']);
            }
        }
        return $count;
    }

    private function exportProducts(string $outputDir, OutputInterface $output): void
    {
        /** @var \Mage_Catalog_Model_Resource_Product_Collection $products */
        $products = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addUrlRewrite();

        $userDefinedAttributes = [];
        /** @var \Mage_Catalog_Model_Resource_Product_Attribute_Collection $attrCollection */
        $attrCollection = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter('is_user_defined', 1);
        foreach ($attrCollection as $attr) {
            $userDefinedAttributes[] = $attr->getAttributeCode();
        }

        $data = ['products' => []];

        foreach ($products as $product) {
            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product')->load($product->getId());

            $productData = [
                'sku' => $product->getSku(),
                'type' => $product->getTypeId(),
                'attribute_set' => $this->getAttributeSetName((int) $product->getAttributeSetId()),
                'name' => $product->getName(),
                'price' => (float) $product->getPrice(),
                'status' => (int) $product->getStatus(),
                'visibility' => (int) $product->getVisibility(),
            ];

            if ($product->getDescription()) {
                $productData['description'] = $product->getDescription();
            }
            if ($product->getShortDescription()) {
                $productData['short_description'] = $product->getShortDescription();
            }
            if ($product->getWeight()) {
                $productData['weight'] = (float) $product->getWeight();
            }
            if ($product->getSpecialPrice()) {
                $productData['special_price'] = (float) $product->getSpecialPrice();
            }
            if ($product->getTaxClassId()) {
                $productData['tax_class_id'] = (int) $product->getTaxClassId();
            }

            // Categories (by path)
            $categoryPaths = [];
            foreach ($product->getCategoryIds() as $catId) {
                /** @var Mage_Catalog_Model_Category $category */
                $category = Mage::getModel('catalog/category')->load($catId);
                $path = $this->getCategoryPath($category);
                if ($path) {
                    $categoryPaths[] = $path;
                }
            }
            if (!empty($categoryPaths)) {
                $productData['categories'] = $categoryPaths;
            }

            // Websites
            $websiteCodes = [];
            foreach ($product->getWebsiteIds() as $websiteId) {
                $website = Mage::app()->getWebsite($websiteId);
                $websiteCodes[] = $website->getCode();
            }
            $productData['websites'] = $websiteCodes;

            // Custom attributes
            $customAttrs = [];
            foreach ($userDefinedAttributes as $attrCode) {
                $value = $product->getAttributeText($attrCode) ?: $product->getData($attrCode);
                if ($value !== null && $value !== false && $value !== '') {
                    $customAttrs[$attrCode] = $value;
                }
            }
            if (!empty($customAttrs)) {
                $productData['attributes'] = $customAttrs;
            }

            // Images
            $images = $this->getProductImages($product);
            if (!empty($images)) {
                $productData['images'] = $images;
            }

            // Stock
            /** @var \Mage_CatalogInventory_Model_Stock_Item $stockItem */
            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            if ($stockItem->getId()) {
                $productData['stock'] = [
                    'qty' => (int) $stockItem->getQty(),
                    'is_in_stock' => (int) $stockItem->getIsInStock(),
                ];
            }

            // Configurable products
            if ($product->getTypeId() === 'configurable') {
                $this->addConfigurableData($product, $productData);
            }

            // Bundle products
            if ($product->getTypeId() === 'bundle') {
                $this->addBundleData($product, $productData);
            }

            // Downloadable products
            if ($product->getTypeId() === 'downloadable') {
                $this->addDownloadableData($product, $productData);
            }

            // Grouped products
            if ($product->getTypeId() === 'grouped') {
                $this->addGroupedData($product, $productData);
            }

            $data['products'][] = $productData;
        }

        $this->saveJson($outputDir . '/products.json', $data);
        $output->writeln('  Saved: products.json (' . count($data['products']) . ' products)');
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductImages(Mage_Catalog_Model_Product $product): array
    {
        $images = [];

        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            $images['image'] = $product->getImage();
        }
        if ($product->getSmallImage() && $product->getSmallImage() !== 'no_selection') {
            $images['small_image'] = $product->getSmallImage();
        }
        if ($product->getThumbnail() && $product->getThumbnail() !== 'no_selection') {
            $images['thumbnail'] = $product->getThumbnail();
        }

        // Gallery
        $gallery = $product->getMediaGalleryImages();
        if ($gallery && $gallery->getSize() > 0) {
            $galleryImages = [];
            $baseUrl = Mage::getBaseUrl('media') . 'catalog/product';
            foreach ($gallery as $image) {
                $galleryImages[] = str_replace($baseUrl, '', $image->getUrl());
            }
            if (count($galleryImages) > 1) {
                $images['gallery'] = $galleryImages;
            }
        }

        return $images;
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function addConfigurableData(Mage_Catalog_Model_Product $product, array &$productData): void
    {
        try {
            /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
            $typeInstance = $product->getTypeInstance(true);
            $configurableAttrs = $typeInstance->getConfigurableAttributesAsArray($product);

            if (!empty($configurableAttrs)) {
                $productData['configurable_attributes'] = array_column($configurableAttrs, 'attribute_code');

                $childProducts = $typeInstance->getUsedProducts(null, $product);
                $productData['associated_skus'] = array_map(
                    fn(Mage_Catalog_Model_Product $p) => $p->getSku(),
                    $childProducts,
                );
            }
        } catch (\Throwable $e) {
            // Skip configurable data if it can't be retrieved
        }
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function addBundleData(Mage_Catalog_Model_Product $product, array &$productData): void
    {
        try {
            $productData['price_type'] = $product->getPriceType() == 1 ? 'fixed' : 'dynamic';

            /** @var \Mage_Bundle_Model_Product_Type $typeInstance */
            $typeInstance = $product->getTypeInstance(true);
            $optionsCollection = $typeInstance->getOptionsCollection($product);
            $bundleOptions = [];

            foreach ($optionsCollection as $option) {
                $selections = $typeInstance->getSelectionsCollection(
                    [$option->getId()],
                    $product,
                );

                $optionData = [
                    'title' => $option->getTitle(),
                    'type' => $option->getType(),
                    'required' => (int) $option->getRequired(),
                    'products' => [],
                ];

                foreach ($selections as $selection) {
                    $optionData['products'][] = [
                        'sku' => $selection->getSku(),
                        'qty' => (int) $selection->getSelectionQty(),
                        'is_default' => (int) $selection->getIsDefault(),
                    ];
                }

                $bundleOptions[] = $optionData;
            }

            if (!empty($bundleOptions)) {
                $productData['bundle_options'] = $bundleOptions;
            }
        } catch (\Throwable $e) {
            // Skip bundle data if it can't be retrieved
        }
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function addDownloadableData(Mage_Catalog_Model_Product $product, array &$productData): void
    {
        try {
            /** @var \Mage_Downloadable_Model_Resource_Link_Collection $links */
            $links = Mage::getModel('downloadable/link')->getCollection()
                ->addProductToFilter($product->getId());

            $linksData = [];
            /** @var Mage_Downloadable_Model_Link $link */
            foreach ($links as $link) {
                $linksData[] = [
                    'title' => $link->getTitle(),
                    'price' => (float) $link->getPrice(),
                    'file' => $link->getLinkFile(),
                    'downloads' => (int) $link->getNumberOfDownloads(),
                ];
            }

            if (!empty($linksData)) {
                $productData['links'] = $linksData;
            }
        } catch (\Throwable $e) {
            // Skip downloadable data if it can't be retrieved
        }
    }

    /**
     * @param array<string, mixed> $productData
     */
    private function addGroupedData(Mage_Catalog_Model_Product $product, array &$productData): void
    {
        try {
            /** @var \Mage_Catalog_Model_Product_Type_Grouped $typeInstance */
            $typeInstance = $product->getTypeInstance(true);
            $associatedProducts = $typeInstance->getAssociatedProducts($product);

            $associatedData = [];
            foreach ($associatedProducts as $associated) {
                $associatedData[] = [
                    'sku' => $associated->getSku(),
                    'qty' => (float) $associated->getQty(),
                    'position' => (int) $associated->getPosition(),
                ];
            }

            if (!empty($associatedData)) {
                $productData['associated_products'] = $associatedData;
            }
        } catch (\Throwable $e) {
            // Skip grouped data if it can't be retrieved
        }
    }

    private function getAttributeSetName(int $setId): string
    {
        /** @var \Mage_Eav_Model_Entity_Attribute_Set $set */
        $set = Mage::getModel('eav/entity_attribute_set')->load($setId);
        return $set->getAttributeSetName() ?: 'Default';
    }

    private function getCategoryPath(Mage_Catalog_Model_Category $category): ?string
    {
        $pathIds = explode('/', (string) $category->getPath());
        // Remove root categories (1 and 2)
        $pathIds = array_slice($pathIds, 2);

        if (empty($pathIds)) {
            return null;
        }

        $names = [];
        foreach ($pathIds as $id) {
            /** @var Mage_Catalog_Model_Category $cat */
            $cat = Mage::getModel('catalog/category')->load($id);
            $names[] = $cat->getName();
        }

        return implode('/', $names);
    }

    private function exportCms(string $outputDir, OutputInterface $output): void
    {
        $data = ['pages' => [], 'blocks' => []];

        // Pages
        /** @var \Mage_Cms_Model_Resource_Page_Collection $pages */
        $pages = Mage::getModel('cms/page')->getCollection();
        /** @var Mage_Cms_Model_Page $page */
        foreach ($pages as $page) {
            $storeIds = $page->getResource()->lookupStoreIds($page->getId());
            $data['pages'][] = [
                'identifier' => $page->getIdentifier(),
                'title' => $page->getTitle(),
                'content_heading' => $page->getContentHeading(),
                'content' => $page->getContent(),
                'root_template' => $page->getRootTemplate(),
                'is_active' => (int) $page->getIsActive(),
                'stores' => $storeIds ?: [0],
            ];
        }

        // Blocks
        /** @var \Mage_Cms_Model_Resource_Block_Collection $blocks */
        $blocks = Mage::getModel('cms/block')->getCollection();
        /** @var Mage_Cms_Model_Block $block */
        foreach ($blocks as $block) {
            $storeIds = $block->getResource()->lookupStoreIds($block->getId());
            $data['blocks'][] = [
                'identifier' => $block->getIdentifier(),
                'title' => $block->getTitle(),
                'content' => $block->getContent(),
                'is_active' => (int) $block->getIsActive(),
                'stores' => $storeIds ?: [0],
            ];
        }

        $this->saveJson($outputDir . '/cms.json', $data);
        $output->writeln('  Saved: cms.json (' . count($data['pages']) . ' pages, ' . count($data['blocks']) . ' blocks)');
    }

    private function exportBlog(string $outputDir, OutputInterface $output): void
    {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            $output->writeln('  Skipped: blog.json (module not enabled)');
            return;
        }

        /** @var \Maho_Blog_Model_Resource_Post_Collection|false $posts */
        $posts = Mage::getModel('blog/post')->getCollection();
        if ($posts === false) {
            $output->writeln('  Skipped: blog.json (collection not available)');
            return;
        }

        $posts->addAttributeToSelect('*');
        $data = ['posts' => []];

        foreach ($posts as $post) {
            $postData = [
                'url_key' => $post->getUrlKey(),
                'title' => $post->getTitle(),
                'content' => $post->getContent(),
                'is_active' => (int) $post->getIsActive(),
                'store_ids' => $post->getStoreIds() ?: [0],
                'publish_date' => $post->getPublishDate(),
            ];

            if ($post->getImage()) {
                $postData['image'] = $post->getImage();
            }
            if ($post->getMetaTitle()) {
                $postData['meta_title'] = $post->getMetaTitle();
            }
            if ($post->getMetaDescription()) {
                $postData['meta_description'] = $post->getMetaDescription();
            }
            if ($post->getMetaKeywords()) {
                $postData['meta_keywords'] = $post->getMetaKeywords();
            }

            $data['posts'][] = $postData;
        }

        $this->saveJson($outputDir . '/blog.json', $data);
        $output->writeln('  Saved: blog.json (' . count($data['posts']) . ' posts)');
    }

    private function exportReviews(string $outputDir, OutputInterface $output): void
    {
        /** @var \Mage_Review_Model_Resource_Review_Collection $reviews */
        $reviews = Mage::getModel('review/review')->getCollection()
            ->addStatusFilter(Mage_Review_Model_Review::STATUS_APPROVED);

        $data = ['reviews' => []];

        /** @var Mage_Review_Model_Review $review */
        foreach ($reviews as $review) {
            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product')->load($review->getEntityPkValue());
            if (!$product->getId()) {
                continue;
            }

            $reviewData = [
                'product_sku' => $product->getSku(),
                'title' => $review->getTitle(),
                'detail' => $review->getDetail(),
                'nickname' => $review->getNickname(),
                'status' => 1,
            ];

            // Get ratings
            /** @var \Mage_Rating_Model_Resource_Rating_Option_Vote_Collection|false $votes */
            $votes = Mage::getModel('rating/rating_option_vote')->getCollection();
            if ($votes === false) {
                continue;
            }
            $votes->setReviewFilter($review->getId());

            $ratings = [];
            foreach ($votes as $vote) {
                /** @var \Mage_Rating_Model_Rating $rating */
                $rating = Mage::getModel('rating/rating')->load($vote->getRatingId());
                $ratings[$rating->getRatingCode()] = (int) ($vote->getPercent() / 20); // Convert to 1-5
            }

            if (!empty($ratings)) {
                $reviewData['ratings'] = $ratings;
            }

            $data['reviews'][] = $reviewData;
        }

        $this->saveJson($outputDir . '/reviews.json', $data);
        $output->writeln('  Saved: reviews.json (' . count($data['reviews']) . ' reviews)');
    }

    private function exportStaticData(string $outputDir, OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        // Tax classes
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($connection->getTableName('tax_class'))
                ->where('class_id > ?', 1),
        );
        $taxClasses = [];
        foreach ($rows as $row) {
            $taxClasses[] = [
                'class_id' => (int) $row['class_id'],
                'class_name' => $row['class_name'],
                'class_type' => $row['class_type'],
            ];
        }
        $this->saveJson($outputDir . '/tax_classes.json', ['tax_classes' => $taxClasses]);
        $output->writeln('  Saved: tax_classes.json (' . count($taxClasses) . ' entries)');

        // Tax rates
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_calculation_rate')),
        );
        $taxRates = [];
        foreach ($rows as $row) {
            $taxRates[] = [
                'tax_calculation_rate_id' => (int) $row['tax_calculation_rate_id'],
                'tax_country_id' => $row['tax_country_id'],
                'tax_region_id' => (int) $row['tax_region_id'],
                'tax_postcode' => $row['tax_postcode'],
                'code' => $row['code'],
                'rate' => (float) $row['rate'],
            ];
        }
        $this->saveJson($outputDir . '/tax_rates.json', ['tax_rates' => $taxRates]);
        $output->writeln('  Saved: tax_rates.json (' . count($taxRates) . ' entries)');

        // Customer groups
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($connection->getTableName('customer_group'))
                ->where('customer_group_id > ?', 1),
        );
        $customerGroups = [];
        foreach ($rows as $row) {
            $customerGroups[] = [
                'customer_group_id' => (int) $row['customer_group_id'],
                'customer_group_code' => $row['customer_group_code'],
                'tax_class_id' => (int) $row['tax_class_id'],
            ];
        }
        $this->saveJson($outputDir . '/customer_groups.json', ['customer_groups' => $customerGroups]);
        $output->writeln('  Saved: customer_groups.json (' . count($customerGroups) . ' entries)');

        // Ratings - includes rating, rating_option, and rating_store
        $ratingsData = [
            'ratings' => [],
            'rating_options' => [],
            'rating_stores' => [],
        ];

        // Main rating definitions
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('rating')),
        );
        foreach ($rows as $row) {
            $ratingsData['ratings'][] = [
                'rating_id' => (int) $row['rating_id'],
                'entity_id' => (int) $row['entity_id'],
                'rating_code' => $row['rating_code'],
                'position' => (int) $row['position'],
            ];
        }

        // Rating options (1-5 scale for each rating)
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('rating_option')),
        );
        foreach ($rows as $row) {
            $ratingsData['rating_options'][] = [
                'option_id' => (int) $row['option_id'],
                'rating_id' => (int) $row['rating_id'],
                'code' => $row['code'],
                'value' => (int) $row['value'],
                'position' => (int) $row['position'],
            ];
        }

        // Rating store associations
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('rating_store')),
        );
        foreach ($rows as $row) {
            $ratingsData['rating_stores'][] = [
                'rating_id' => (int) $row['rating_id'],
                'store_id' => (int) $row['store_id'],
            ];
        }

        $this->saveJson($outputDir . '/ratings.json', $ratingsData);
        $output->writeln('  Saved: ratings.json (' . count($ratingsData['ratings']) . ' ratings, ' . count($ratingsData['rating_options']) . ' options)');

        // Tax rules and calculations
        $taxRulesData = [
            'tax_rules' => [],
            'tax_calculations' => [],
        ];

        // Tax calculation rules
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_calculation_rule')),
        );
        foreach ($rows as $row) {
            $taxRulesData['tax_rules'][] = [
                'tax_calculation_rule_id' => (int) $row['tax_calculation_rule_id'],
                'code' => $row['code'],
                'priority' => (int) $row['priority'],
                'position' => (int) $row['position'],
                'calculate_subtotal' => (int) $row['calculate_subtotal'],
            ];
        }

        // Tax calculations (links rates to rules, customer classes, product classes)
        // We need to convert IDs to codes for portability
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_calculation')),
        );

        // Build lookup maps for tax classes
        $taxClassMap = [];
        $taxClassRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_class'), ['class_id', 'class_name']),
        );
        foreach ($taxClassRows as $tcRow) {
            $taxClassMap[(int) $tcRow['class_id']] = $tcRow['class_name'];
        }

        // Build lookup map for tax rates
        $taxRateMap = [];
        $taxRateRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_calculation_rate'), ['tax_calculation_rate_id', 'code']),
        );
        foreach ($taxRateRows as $trRow) {
            $taxRateMap[(int) $trRow['tax_calculation_rate_id']] = $trRow['code'];
        }

        // Build lookup map for tax rules
        $taxRuleMap = [];
        $taxRuleRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('tax_calculation_rule'), ['tax_calculation_rule_id', 'code']),
        );
        foreach ($taxRuleRows as $trRow) {
            $taxRuleMap[(int) $trRow['tax_calculation_rule_id']] = $trRow['code'];
        }

        foreach ($rows as $row) {
            $taxRulesData['tax_calculations'][] = [
                'tax_rate_code' => $taxRateMap[(int) $row['tax_calculation_rate_id']] ?? null,
                'tax_rule_code' => $taxRuleMap[(int) $row['tax_calculation_rule_id']] ?? null,
                'customer_tax_class_name' => $taxClassMap[(int) $row['customer_tax_class_id']] ?? null,
                'product_tax_class_name' => $taxClassMap[(int) $row['product_tax_class_id']] ?? null,
            ];
        }

        $this->saveJson($outputDir . '/tax_rules.json', $taxRulesData);
        $output->writeln('  Saved: tax_rules.json (' . count($taxRulesData['tax_rules']) . ' rules, ' . count($taxRulesData['tax_calculations']) . ' calculations)');
    }

    /**
     * Convert attribute IDs to codes in configswatches settings
     */
    private function convertAttributeIdsToCodesInConfig(string $path, string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // These paths contain attribute IDs that should be converted to codes
        $idPaths = [
            'configswatches/general/product_list_attribute',
            'configswatches/general/swatch_attributes',
        ];

        if (!in_array($path, $idPaths)) {
            return $value;
        }

        // Split by comma for multiple IDs
        $ids = array_map('trim', explode(',', $value));
        $codes = [];

        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                $codes[] = $id; // Already a code
                continue;
            }

            /** @var \Mage_Eav_Model_Entity_Attribute $attribute */
            $attribute = Mage::getModel('eav/entity_attribute')->load((int) $id);
            if ($attribute->getId()) {
                $codes[] = $attribute->getAttributeCode();
            }
        }

        return implode(',', $codes);
    }

    private function exportProductLinks(string $outputDir, OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        $data = [
            'related' => [],
            'upsell' => [],
            'crosssell' => [],
        ];

        // Link type IDs
        $linkTypes = [
            \Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED => 'related',
            \Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL => 'upsell',
            \Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL => 'crosssell',
        ];

        // Build product ID to SKU map
        $productSkuMap = [];
        $productRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('catalog_product_entity'), ['entity_id', 'sku']),
        );
        foreach ($productRows as $row) {
            $productSkuMap[(int) $row['entity_id']] = $row['sku'];
        }

        // Fetch all product links
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($connection->getTableName('catalog_product_link'))
                ->where('link_type_id IN (?)', array_keys($linkTypes)),
        );

        foreach ($rows as $row) {
            $linkType = $linkTypes[(int) $row['link_type_id']] ?? null;
            if (!$linkType) {
                continue;
            }

            $productSku = $productSkuMap[(int) $row['product_id']] ?? null;
            $linkedSku = $productSkuMap[(int) $row['linked_product_id']] ?? null;

            if ($productSku && $linkedSku) {
                $data[$linkType][] = [
                    'product_sku' => $productSku,
                    'linked_sku' => $linkedSku,
                ];
            }
        }

        $total = count($data['related']) + count($data['upsell']) + count($data['crosssell']);
        $this->saveJson($outputDir . '/product_links.json', $data);
        $output->writeln("  Saved: product_links.json ({$total} links: " .
            count($data['related']) . ' related, ' .
            count($data['upsell']) . ' upsell, ' .
            count($data['crosssell']) . ' crosssell)');
    }

    private function exportTierPrices(string $outputDir, OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        $data = ['tier_prices' => []];

        // Build product ID to SKU map
        $productSkuMap = [];
        $productRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('catalog_product_entity'), ['entity_id', 'sku']),
        );
        foreach ($productRows as $row) {
            $productSkuMap[(int) $row['entity_id']] = $row['sku'];
        }

        // Build customer group ID to code map
        $customerGroupMap = [];
        $customerGroupRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('customer_group'), ['customer_group_id', 'customer_group_code']),
        );
        foreach ($customerGroupRows as $row) {
            $customerGroupMap[(int) $row['customer_group_id']] = $row['customer_group_code'];
        }

        // Fetch tier prices
        $rows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('catalog_product_entity_tier_price')),
        );

        foreach ($rows as $row) {
            $productSku = $productSkuMap[(int) $row['entity_id']] ?? null;
            if (!$productSku) {
                continue;
            }

            $tierPrice = [
                'product_sku' => $productSku,
                'qty' => (float) $row['qty'],
                'value' => (float) $row['value'],
                'website_id' => (int) $row['website_id'],
            ];

            if ((int) $row['all_groups'] === 1) {
                $tierPrice['all_groups'] = true;
            } else {
                $tierPrice['customer_group_code'] = $customerGroupMap[(int) $row['customer_group_id']] ?? 'NOT LOGGED IN';
            }

            $data['tier_prices'][] = $tierPrice;
        }

        $this->saveJson($outputDir . '/tier_prices.json', $data);
        $output->writeln('  Saved: tier_prices.json (' . count($data['tier_prices']) . ' entries)');
    }

    private function exportCustomOptions(string $outputDir, OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        $data = ['custom_options' => []];

        // Build product ID to SKU map
        $productSkuMap = [];
        $productRows = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('catalog_product_entity'), ['entity_id', 'sku']),
        );
        foreach ($productRows as $row) {
            $productSkuMap[(int) $row['entity_id']] = $row['sku'];
        }

        // Fetch options
        $options = $connection->fetchAll(
            $connection->select()->from($connection->getTableName('catalog_product_option')),
        );

        foreach ($options as $option) {
            $productSku = $productSkuMap[(int) $option['product_id']] ?? null;
            if (!$productSku) {
                continue;
            }

            $optionId = (int) $option['option_id'];

            // Get option title
            $titleRow = $connection->fetchRow(
                $connection->select()
                    ->from($connection->getTableName('catalog_product_option_title'))
                    ->where('option_id = ?', $optionId)
                    ->where('store_id = ?', 0),
            );

            // Get option price (for non-select types)
            $priceRow = $connection->fetchRow(
                $connection->select()
                    ->from($connection->getTableName('catalog_product_option_price'))
                    ->where('option_id = ?', $optionId)
                    ->where('store_id = ?', 0),
            );

            $optionData = [
                'product_sku' => $productSku,
                'type' => $option['type'],
                'is_require' => (int) $option['is_require'],
                'sort_order' => (int) $option['sort_order'],
                'title' => $titleRow['title'] ?? '',
            ];

            if (!empty($option['sku'])) {
                $optionData['sku'] = $option['sku'];
            }
            if (!empty($option['max_characters'])) {
                $optionData['max_characters'] = (int) $option['max_characters'];
            }
            if (!empty($option['file_extension'])) {
                $optionData['file_extension'] = $option['file_extension'];
            }
            if (!empty($option['image_size_x'])) {
                $optionData['image_size_x'] = (int) $option['image_size_x'];
            }
            if (!empty($option['image_size_y'])) {
                $optionData['image_size_y'] = (int) $option['image_size_y'];
            }
            if ($priceRow) {
                $optionData['price'] = (float) $priceRow['price'];
                $optionData['price_type'] = $priceRow['price_type'];
            }

            // For select types, get the option values
            if (in_array($option['type'], ['drop_down', 'radio', 'checkbox', 'multiple'])) {
                $values = $connection->fetchAll(
                    $connection->select()
                        ->from($connection->getTableName('catalog_product_option_type_value'))
                        ->where('option_id = ?', $optionId)
                        ->order('sort_order ASC'),
                );

                $optionValues = [];
                foreach ($values as $value) {
                    $valueId = (int) $value['option_type_id'];

                    // Get value title
                    $valueTitleRow = $connection->fetchRow(
                        $connection->select()
                            ->from($connection->getTableName('catalog_product_option_type_title'))
                            ->where('option_type_id = ?', $valueId)
                            ->where('store_id = ?', 0),
                    );

                    // Get value price
                    $valuePriceRow = $connection->fetchRow(
                        $connection->select()
                            ->from($connection->getTableName('catalog_product_option_type_price'))
                            ->where('option_type_id = ?', $valueId)
                            ->where('store_id = ?', 0),
                    );

                    $valueData = [
                        'title' => $valueTitleRow['title'] ?? '',
                        'sort_order' => (int) $value['sort_order'],
                    ];

                    if (!empty($value['sku'])) {
                        $valueData['sku'] = $value['sku'];
                    }
                    if ($valuePriceRow) {
                        $valueData['price'] = (float) $valuePriceRow['price'];
                        $valueData['price_type'] = $valuePriceRow['price_type'];
                    }

                    $optionValues[] = $valueData;
                }

                if (!empty($optionValues)) {
                    $optionData['values'] = $optionValues;
                }
            }

            $data['custom_options'][] = $optionData;
        }

        $this->saveJson($outputDir . '/custom_options.json', $data);
        $output->writeln('  Saved: custom_options.json (' . count($data['custom_options']) . ' options)');
    }

    private function exportDynamicCategoryRules(string $outputDir, OutputInterface $output): void
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        $data = ['dynamic_category_rules' => []];

        // Check if table exists
        $tableName = $connection->getTableName('catalog_category_dynamic_rule');
        try {
            $rows = $connection->fetchAll(
                $connection->select()->from($tableName),
            );
        } catch (Exception $e) {
            $output->writeln('  Skipped: dynamic_category_rules.json (table not found)');
            return;
        }

        // Build category path map for portability
        foreach ($rows as $row) {
            $categoryId = (int) $row['category_id'];

            // Get category path by name
            /** @var Mage_Catalog_Model_Category $category */
            $category = Mage::getModel('catalog/category')->load($categoryId);
            $categoryPath = $this->getCategoryPath($category);

            if (!$categoryPath) {
                continue;
            }

            $data['dynamic_category_rules'][] = [
                'category_path' => $categoryPath,
                'conditions_serialized' => $row['conditions_serialized'],
                'is_active' => (int) $row['is_active'],
            ];
        }

        $this->saveJson($outputDir . '/dynamic_category_rules.json', $data);
        $output->writeln('  Saved: dynamic_category_rules.json (' . count($data['dynamic_category_rules']) . ' rules)');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveJson(string $file, array $data): void
    {
        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
