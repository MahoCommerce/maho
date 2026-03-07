<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Blog_Model_Observer
{
    public function setBlogEntityKey(\Maho\Event\Observer $observer): void
    {
        $entityKey = 'blog_index';
        if ($post = Mage::registry('current_blog_post')) {
            $entityKey = 'blog_post_' . $post->getId();
        } elseif ($category = Mage::registry('current_blog_category')) {
            $entityKey = 'blog_category_' . $category->getId();
        }

        Mage::register('current_entity_key', $entityKey, true);
    }

    public function addBlogToTopmenuItems(\Maho\Event\Observer $observer): void
    {
        if (!Mage::helper('blog')->shouldShowInNavigation()) {
            return;
        }

        /** @var \Maho\Data\Tree\Node $menu */
        $menu = $observer->getMenu();
        $tree = $menu->getTree();

        $isBlogActive = Mage::app()->getRequest()->getModuleName() === 'blog';
        $helper = Mage::helper('blog');

        $blogNode = new \Maho\Data\Tree\Node([
            'name' => $helper->__('Blog'),
            'id' => 'blog-node',
            'url' => $helper->getBlogUrl(),
            'has_active' => $isBlogActive && $helper->areCategoriesEnabled(),
            'is_active' => $isBlogActive,
        ], 'id', $tree, $menu);

        $menu->addChild($blogNode);

        // Add category children under blog node when categories are enabled
        if ($helper->areCategoriesEnabled()) {
            $currentCategory = Mage::registry('current_blog_category');
            $categories = Mage::getResourceModel('blog/category_collection')
                ->addRootFilter()
                ->addActiveFilter()
                ->addStoreFilter(Mage::app()->getStore())
                ->addParentFilter(Maho_Blog_Model_Category::ROOT_PARENT_ID)
                ->setOrder('position', 'ASC');

            foreach ($categories as $category) {
                $isActive = $currentCategory && (int) $currentCategory->getId() === (int) $category->getId();
                $categoryNode = new \Maho\Data\Tree\Node([
                    'name' => $category->getName(),
                    'id' => 'blog-category-' . $category->getId(),
                    'url' => $category->getUrl(),
                    'has_active' => false,
                    'is_active' => $isActive,
                ], 'id', $tree, $blogNode);
                $blogNode->addChild($categoryNode);
            }
        }
    }

    /**
     * Add blog content to sitemap generation
     */
    public function addBlogToSitemap(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Sitemap_Model_Sitemap $sitemap */
        $sitemap = $observer->getEvent()->getSitemap();
        $storeId = (int) $observer->getEvent()->getStoreId();
        $baseUrl = $observer->getEvent()->getBaseUrl();
        $date = $observer->getEvent()->getDate();
        $maxUrlsPerFile = (int) $observer->getEvent()->getMaxUrlsPerFile();


        // Get blog posts collection for sitemap
        $posts = $this->getBlogPostsForSitemap($storeId);
        if (empty($posts)) {
            return;
        }

        // Get blog sitemap configuration
        $changefreq = (string) Mage::getStoreConfig('blog/sitemap/changefreq', $storeId);
        $priority = (float) Mage::getStoreConfig('blog/sitemap/priority', $storeId);
        $lastmod = Mage::getStoreConfigFlag('blog/sitemap/lastmod', $storeId) ? $date : '';
        $includeBlogImages = Mage::getStoreConfigFlag('blog/sitemap/include_images', $storeId);

        // Prepare items including blog index
        $blogItems = [];

        // Add blog index as first item
        $blogIndex = new \Maho\DataObject();
        $blogIndex->setUrl(str_replace($baseUrl, '', Mage::helper('blog')->getBlogUrl($storeId)));
        $blogItems[] = $blogIndex;

        // Add all blog posts
        foreach ($posts as $post) {
            $blogPost = new \Maho\DataObject();
            $blogPost->setUrl(str_replace($baseUrl, '', $post->getUrl()));

            // Only set image data if the post has an image AND images are enabled in configuration
            if ($includeBlogImages && $post->hasImage()) {
                $blogPost->setImageUrl($post->getImageUrl());
                $blogPost->setImageTitle($post->getTitle()); // Use post title as image title (same as frontend alt text)
            }
            $blogItems[] = $blogPost;
        }

        // Add category URLs when categories are enabled
        if (Mage::helper('blog')->areCategoriesEnabled()) {
            $categories = $this->getBlogCategoriesForSitemap($storeId);
            foreach ($categories as $category) {
                $blogCategory = new \Maho\DataObject();
                $blogCategory->setUrl(str_replace($baseUrl, '', $category->getUrl()));
                $blogItems[] = $blogCategory;
            }
        }

        // Generate blog sitemap files
        $this->writeBlogSitemapFiles(
            $sitemap,
            $blogItems,
            $baseUrl,
            $lastmod,
            $changefreq,
            $priority,
            $maxUrlsPerFile,
        );
    }

    /**
     * Write blog sitemap files
     */
    protected function writeBlogSitemapFiles(
        Mage_Sitemap_Model_Sitemap $sitemap,
        array $items,
        string $baseUrl,
        string $lastmod,
        string $changefreq,
        float $priority,
        int $maxUrlsPerFile,
    ): void {
        if (empty($items)) {
            return;
        }

        $itemCount = count($items);
        $fileCount = 1;
        $currentFileItemCount = 0;
        $io = null;

        foreach ($items as $index => $item) {
            // Start new file if needed
            if ($currentFileItemCount === 0) {
                if ($io) {
                    $io->streamWrite('</urlset>');
                    $io->streamClose();
                }

                $filename = $this->getBlogSitemapFilename($sitemap, $fileCount, $itemCount, $maxUrlsPerFile);
                $io = $this->openBlogSitemapFile($sitemap, $filename);
                $sitemap->addSitemapFile($filename, $lastmod);
            }

            // Write URL to sitemap
            $xml = $this->getSitemapRow($baseUrl . $item->getUrl(), $lastmod, $changefreq, $priority, $item->getImageUrl(), $item->getImageTitle());
            $io->streamWrite($xml);

            $currentFileItemCount++;

            // Check if we need to start a new file
            if ($currentFileItemCount >= $maxUrlsPerFile && $index < $itemCount - 1) {
                $currentFileItemCount = 0;
                $fileCount++;
            }
        }

        // Close last file
        if ($io) {
            $io->streamWrite('</urlset>');
            $io->streamClose();
        }
    }

    /**
     * Get blog sitemap filename
     */
    protected function getBlogSitemapFilename(Mage_Sitemap_Model_Sitemap $sitemap, int $fileNumber, int $totalItems, int $maxUrlsPerFile): string
    {
        $baseName = pathinfo($sitemap->getSitemapFilename(), PATHINFO_FILENAME);

        // If only one file is needed, use simple naming
        if ($totalItems <= $maxUrlsPerFile) {
            return $baseName . '-blog.xml';
        }

        // Multiple files needed, add number
        return $baseName . '-blog-' . $fileNumber . '.xml';
    }

    /**
     * Open and initialize a blog sitemap file
     */
    protected function openBlogSitemapFile(Mage_Sitemap_Model_Sitemap $sitemap, string $filename): \Maho\Io\File
    {
        $io = new \Maho\Io\File();
        $io->setAllowCreateFolders(true);

        // Files should be saved in public/{sitemap_path} for web accessibility
        $resolvedPath = rtrim(Mage::getBaseDir('public') . '/' . $sitemap->getSitemapPath(), '/');

        $io->open(['path' => $resolvedPath]);

        if ($io->fileExists($filename) && !$io->isWriteable($filename)) {
            Mage::throwException(Mage::helper('sitemap')->__('File "%s" cannot be saved. Please, make sure the directory "%s" is writeable by web server.', $filename, $resolvedPath));
        }

        $io->streamOpen($filename);
        $io->streamWrite('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        $io->streamWrite('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n");

        return $io;
    }

    /**
     * Generate sitemap row XML for a URL
     */
    protected function getSitemapRow(string $url, ?string $lastmod = null, ?string $changefreq = null, ?float $priority = null, ?string $imageUrl = null, ?string $imageTitle = null): string
    {
        $row = '<loc>' . htmlspecialchars($url) . '</loc>';
        if ($lastmod) {
            $row .= '<lastmod>' . $lastmod . '</lastmod>';
        }
        if ($changefreq) {
            $row .= '<changefreq>' . $changefreq . '</changefreq>';
        }
        if ($priority) {
            $row .= sprintf('<priority>%.1f</priority>', $priority);
        }
        if ($imageUrl) {
            $row .= '<image:image>';
            $row .= '<image:loc>' . htmlspecialchars($imageUrl) . '</image:loc>';
            if ($imageTitle) {
                $row .= '<image:title>' . htmlspecialchars($imageTitle) . '</image:title>';
            }
            $row .= '</image:image>';
        }

        return '<url>' . $row . '</url>' . "\n";
    }

    protected function getBlogCategoriesForSitemap(int $storeId): array
    {
        /** @var Maho_Blog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getResourceModel('blog/category_collection')
            ->addRootFilter()
            ->addActiveFilter()
            ->addStoreFilter($storeId);

        return $collection->getItems();
    }

    protected function getBlogPostsForSitemap(int $storeId): array
    {
        $today = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATE_FORMAT);

        /** @var Maho_Blog_Model_Resource_Post_Collection $collection */
        $collection = Mage::getResourceModel('blog/post_collection')
            ->addAttributeToSelect('image')
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('publish_date', [
                ['null' => true],
                ['lteq' => $today],
            ]);

        $posts = [];
        foreach ($collection as $post) {
            $posts[] = $post;
        }

        return $posts;
    }

    public function addBlogAutocompleteContent(\Maho\Event\Observer $observer): void
    {
        $autocompleteData = $observer->getEvent()->getAutocompleteData();
        $layout = $autocompleteData->getLayout();

        $blogBlock = $layout->createBlock('blog/autocomplete');
        if ($blogBlock) {
            $blogHtml = $blogBlock->toHtml();

            // Append blog HTML to existing autocomplete content
            $currentHtml = $autocompleteData->getHtml();
            $autocompleteData->setHtml($currentHtml . $blogHtml);
        }
    }
}
