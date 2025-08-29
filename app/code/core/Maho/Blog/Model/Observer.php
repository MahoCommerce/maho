<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Blog_Model_Observer
{
    public function setBlogEntityKey(Varien_Event_Observer $observer): void
    {
        $entityKey = 'blog_index';
        if ($post = Mage::registry('current_blog_post')) {
            $entityKey = 'blog_post_' . $post->getId();
        }

        Mage::register('current_entity_key', $entityKey, true);
    }

    public function addBlogToTopmenuItems(Varien_Event_Observer $observer): void
    {
        if (!Mage::helper('blog')->shouldShowInNavigation()) {
            return;
        }

        /** @var Varien_Data_Tree_Node $menu */
        $menu = $observer->getMenu();
        $tree = $menu->getTree();

        $blogNode = new Varien_Data_Tree_Node([
            'name' => Mage::helper('blog')->__('Blog'),
            'id' => 'blog-node',
            'url' => Mage::helper('blog')->getBlogUrl(),
            'has_active' => false, // Blog has no children, so always false
            'is_active' => Mage::app()->getRequest()->getModuleName() === 'blog',
        ], 'id', $tree, $menu);

        $menu->addChild($blogNode);
    }

    /**
     * Add blog content to sitemap generation
     */
    public function addBlogToSitemap(Varien_Event_Observer $observer): void
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
        $changefreq = (string) Mage::getStoreConfig('sitemap/blog/changefreq', $storeId);
        $priority = (string) Mage::getStoreConfig('sitemap/blog/priority', $storeId);
        $lastmod = Mage::getStoreConfigFlag('sitemap/blog/lastmod', $storeId) ? $date : '';

        // Prepare items including blog index
        $blogItems = [];

        // Add blog index as first item
        $blogIndex = new Varien_Object();
        $blogIndex->setUrl(str_replace($baseUrl, '', Mage::helper('blog')->getBlogUrl($storeId)));
        $blogItems[] = $blogIndex;

        // Add all blog posts
        foreach ($posts as $post) {
            $blogPost = new Varien_Object();
            $blogPost->setUrl(str_replace($baseUrl, '', $post->getUrl()));
            // Only set image data if the post has an image
            if ($post->hasImage()) {
                $blogPost->setImageUrl($post->getImageUrl());
                $blogPost->setImageTitle($post->getTitle()); // Use post title as image title (same as frontend alt text)
            }
            $blogItems[] = $blogPost;
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
        string $priority,
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
    protected function openBlogSitemapFile(Mage_Sitemap_Model_Sitemap $sitemap, string $filename): Varien_Io_File
    {
        $io = new Varien_Io_File();
        $io->setAllowCreateFolders(true);

        // Files should be saved in public directory for web accessibility
        $resolvedPath = Mage::getBaseDir('public');

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
    protected function getSitemapRow(string $url, ?string $lastmod = null, ?string $changefreq = null, ?string $priority = null, ?string $imageUrl = null, ?string $imageTitle = null): string
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

    public function addBlogAutocompleteContent(Varien_Event_Observer $observer): void
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
