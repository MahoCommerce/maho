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
     * Add blog posts to sitemap generation
     */
    public function addBlogToSitemap(Varien_Event_Observer $observer): void
    {
        $storeId = (int) $observer->getEvent()->getStoreId();
        $date = $observer->getEvent()->getDate();
        $baseUrl = $observer->getEvent()->getBaseUrl();
        $io = $observer->getEvent()->getFile();

        // Get blog posts collection for sitemap
        $posts = $this->getBlogPostsForSitemap($storeId);
        if (empty($posts)) {
            return;
        }

        // Get blog sitemap configuration
        $changefreq = (string) Mage::getStoreConfig('sitemap/blog/changefreq', $storeId);
        $priority = (string) Mage::getStoreConfig('sitemap/blog/priority', $storeId);
        $lastmod = Mage::getStoreConfigFlag('sitemap/blog/lastmod', $storeId) ? $date : '';

        // Add blog index page to sitemap (only if there are posts)
        $blogIndexXml = $this->getSitemapRow(Mage::helper('blog')->getBlogUrl(), $lastmod, $changefreq, $priority);
        $io->streamWrite($blogIndexXml);

        // Write blog posts to sitemap
        foreach ($posts as $post) {
            $xml = $this->getSitemapRow($post->getUrl(), $lastmod, $changefreq, $priority);
            $io->streamWrite($xml);
        }
    }

    /**
     * Generate sitemap row XML for a URL
     */
    protected function getSitemapRow(string $url, ?string $lastmod = null, ?string $changefreq = null, ?string $priority = null): string
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

        return '<url>' . $row . '</url>' . "\n";
    }

    protected function getBlogPostsForSitemap(int $storeId): array
    {
        $today = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATE_FORMAT);

        /** @var Maho_Blog_Model_Resource_Post_Collection $collection */
        $collection = Mage::getResourceModel('blog/post_collection')
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

    /**
     * Handle search autocomplete content collection event
     */
    public function addBlogAutocompleteContent(Varien_Event_Observer $observer): void
    {
        $autocompleteData = $observer->getEvent()->getAutocompleteData();
        $layout = $autocompleteData->getLayout();

        // Create blog autocomplete block
        $blogBlock = $layout->createBlock('blog/autocomplete');

        if ($blogBlock) {
            $blogHtml = $blogBlock->toHtml();

            // Append blog HTML to existing autocomplete content
            $currentHtml = $autocompleteData->getHtml();
            $autocompleteData->setHtml($currentHtml . $blogHtml);
        }
    }
}
