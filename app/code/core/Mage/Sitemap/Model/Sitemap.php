<?php

/**
 * Maho
 *
 * @package    Mage_Sitemap
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2016-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Sitemap_Model_Resource_Sitemap _getResource()
 * @method Mage_Sitemap_Model_Resource_Sitemap getResource()
 * @method Mage_Sitemap_Model_Resource_Sitemap_Collection getCollection()
 *
 * @method int getSitemapId()
 * @method string getSitemapType()
 * @method $this setSitemapType(string $value)
 * @method string getSitemapFilename()
 * @method $this setSitemapFilename(string $value)
 * @method string getSitemapPath()
 * @method $this setSitemapPath(string $value)
 * @method string getSitemapTime()
 * @method $this setSitemapTime(string $value)
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 */
class Mage_Sitemap_Model_Sitemap extends Mage_Core_Model_Abstract
{
    /**
     * Real file path
     *
     * @var string|null
     */
    protected $_filePath;

    /**
     * Array to store generated sitemap files for index
     */
    protected array $_sitemapFiles = [];

    /**
     * Current URL count for splitting logic
     */
    protected int $_currentUrlCount = 0;

    /**
     * Init model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sitemap/sitemap');
    }

    /**
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        $io = new Varien_Io_File();
        $realPath = $io->getCleanPath(Mage::getBaseDir() . '/' . $this->getSitemapPath());

        /**
         * Check path is allow
         */
        if (!$io->allowedPath($realPath, Mage::getBaseDir())) {
            Mage::throwException(Mage::helper('sitemap')->__('Please define correct path'));
        }
        /**
         * Check exists and writeable path
         */
        if (!$io->fileExists($realPath, false)) {
            Mage::throwException(Mage::helper('sitemap')->__('Please create the specified folder "%s" before saving the sitemap.', Mage::helper('core')->escapeHtml($this->getSitemapPath())));
        }

        if (!$io->isWriteable($realPath)) {
            Mage::throwException(Mage::helper('sitemap')->__('Please make sure that "%s" is writable by web-server.', $this->getSitemapPath()));
        }
        /**
         * Check allow filename
         */
        if (!preg_match('#^[a-zA-Z0-9_\.]+$#', $this->getSitemapFilename())) {
            Mage::throwException(Mage::helper('sitemap')->__('Please use only letters (a-z or A-Z), numbers (0-9) or underscore (_) in the filename. No spaces or other characters are allowed.'));
        }
        if (!preg_match('#\.xml$#', $this->getSitemapFilename())) {
            $this->setSitemapFilename($this->getSitemapFilename() . '.xml');
        }

        $this->setSitemapPath(rtrim(str_replace(str_replace('\\', '/', Mage::getBaseDir()), '', $realPath), '/') . '/');

        return parent::_beforeSave();
    }

    /**
     * Return real file path
     *
     * @return string
     */
    protected function getPath()
    {
        if (is_null($this->_filePath)) {
            $this->_filePath = str_replace('//', '/', Mage::getBaseDir() .
                $this->getSitemapPath());
        }
        return $this->_filePath;
    }

    /**
     * Return full file name with path
     *
     * @return string
     */
    public function getPreparedFilename()
    {
        return $this->getPath() . $this->getSitemapFilename();
    }

    /**
     * Generate XML file
     *
     * @return $this
     * @throws Throwable
     */
    public function generateXml()
    {
        $storeId = $this->getStoreId();
        $date = Mage::getSingleton('core/date')->gmtDate(Mage_Core_Model_Locale::DATE_FORMAT);
        $baseUrl = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        $maxUrlsPerFile = (int) Mage::getStoreConfig('sitemap/generate/max_urls_per_file', $storeId);

        // Reset sitemap files array
        $this->_sitemapFiles = [];

        // Generate categories sitemap
        $this->generateCategoriesSitemap($storeId, $baseUrl, $date, $maxUrlsPerFile);

        // Generate products sitemap
        $this->generateProductsSitemap($storeId, $baseUrl, $date, $maxUrlsPerFile);

        // Generate CMS pages sitemap
        $this->generatePagesSitemap($storeId, $baseUrl, $date, $maxUrlsPerFile);

        // Dispatch event for other modules (like blog) to add their sitemaps
        Mage::dispatchEvent('sitemap_urlset_generating_before', [
            'sitemap' => $this,
            'base_url' => $baseUrl,
            'date' => $date,
            'store_id' => $storeId,
            'max_urls_per_file' => $maxUrlsPerFile,
        ]);

        // Generate sitemap index
        $this->generateSitemapIndex($storeId, $baseUrl, $date);

        $this->setSitemapTime(
            Mage::getSingleton('core/date')->gmtDate(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT),
        );
        $this->save();

        return $this;
    }

    /**
     * Get sitemap row
     *
     * @param null|string $lastmod
     * @param null|string $changefreq
     * @param null|string $priority
     */
    protected function getSitemapRow(string $url, $lastmod = null, $changefreq = null, $priority = null): string
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

    /**
     * Generate split sitemaps
     *
     * @throws Throwable
     */
    protected function generateSplitSitemaps(): self
    {
        $storeId = $this->getStoreId();
        $date = Mage::getSingleton('core/date')->gmtDate(Mage_Core_Model_Locale::DATE_FORMAT);
        $baseUrl = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        $maxUrlsPerFile = (int) Mage::getStoreConfig('sitemap/generate/max_urls_per_file', $storeId);

        // Reset sitemap files array
        $this->_sitemapFiles = [];

        // Generate categories sitemap
        $this->generateCategoriesSitemap($storeId, $baseUrl, $date, $maxUrlsPerFile);

        // Generate products sitemap
        $this->generateProductsSitemap($storeId, $baseUrl, $date, $maxUrlsPerFile);

        // Generate CMS pages sitemap
        $this->generatePagesSitemap($storeId, $baseUrl, $date, $maxUrlsPerFile);

        // Dispatch event for other modules (like blog) to add their sitemaps
        Mage::dispatchEvent('sitemap_urlset_generating_before', [
            'sitemap' => $this,
            'base_url' => $baseUrl,
            'date' => $date,
            'store_id' => $storeId,
            'max_urls_per_file' => $maxUrlsPerFile,
        ]);

        // Generate sitemap index
        $this->generateSitemapIndex($storeId, $baseUrl, $date);

        $this->setSitemapTime(
            Mage::getSingleton('core/date')->gmtDate(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT),
        );
        $this->save();

        return $this;
    }

    /**
     * Generate categories sitemap files
     */
    protected function generateCategoriesSitemap(int $storeId, string $baseUrl, string $date, int $maxUrlsPerFile): void
    {
        $changefreq = (string) Mage::getStoreConfig('sitemap/category/changefreq', $storeId);
        $priority = (string) Mage::getStoreConfig('sitemap/category/priority', $storeId);
        $lastmod = Mage::getStoreConfigFlag('sitemap/category/lastmod', $storeId) ? $date : '';

        $collection = Mage::getResourceModel('sitemap/catalog_category')->getCollection($storeId);
        $categories = new Varien_Object();
        $categories->setItems($collection);

        Mage::dispatchEvent('sitemap_categories_generating_before', [
            'collection' => $categories,
            'store_id' => $storeId,
        ]);

        $this->writeSitemapFiles(
            'categories',
            $categories->getItems(),
            $baseUrl,
            $lastmod,
            $changefreq,
            $priority,
            $maxUrlsPerFile,
        );

        unset($collection);
    }

    /**
     * Generate products sitemap files
     */
    protected function generateProductsSitemap(int $storeId, string $baseUrl, string $date, int $maxUrlsPerFile): void
    {
        $changefreq = (string) Mage::getStoreConfig('sitemap/product/changefreq', $storeId);
        $priority = (string) Mage::getStoreConfig('sitemap/product/priority', $storeId);
        $lastmod = Mage::getStoreConfigFlag('sitemap/product/lastmod', $storeId) ? $date : '';

        $collection = Mage::getResourceModel('sitemap/catalog_product')->getCollection($storeId);
        $products = new Varien_Object();
        $products->setItems($collection);

        Mage::dispatchEvent('sitemap_products_generating_before', [
            'collection' => $products,
            'store_id' => $storeId,
        ]);

        $this->writeSitemapFiles(
            'products',
            $products->getItems(),
            $baseUrl,
            $lastmod,
            $changefreq,
            $priority,
            $maxUrlsPerFile,
        );

        unset($collection);
    }

    /**
     * Generate CMS pages sitemap files
     */
    protected function generatePagesSitemap(int $storeId, string $baseUrl, string $date, int $maxUrlsPerFile): void
    {
        $homepage = (string) Mage::getStoreConfig('web/default/cms_home_page', $storeId);
        $changefreq = (string) Mage::getStoreConfig('sitemap/page/changefreq', $storeId);
        $priority = (string) Mage::getStoreConfig('sitemap/page/priority', $storeId);
        $lastmod = Mage::getStoreConfigFlag('sitemap/page/lastmod', $storeId) ? $date : '';

        $collection = Mage::getResourceModel('sitemap/cms_page')->getCollection($storeId);
        $pages = new Varien_Object();
        $pages->setItems($collection);

        Mage::dispatchEvent('sitemap_cms_pages_generating_before', [
            'collection' => $pages,
            'store_id' => $storeId,
        ]);

        // Process pages to handle homepage URL
        $pageItems = [];
        foreach ($pages->getItems() as $item) {
            $url = $item->getUrl();
            if ($url == $homepage) {
                $url = '';
            }
            $item->setUrl($url);
            $pageItems[] = $item;
        }

        $this->writeSitemapFiles(
            'pages',
            $pageItems,
            $baseUrl,
            $lastmod,
            $changefreq,
            $priority,
            $maxUrlsPerFile,
        );

        unset($collection);
    }

    /**
     * Write sitemap files for a content type with splitting
     */
    protected function writeSitemapFiles(string $type, array $items, string $baseUrl, string $lastmod, string $changefreq, string $priority, int $maxUrlsPerFile): void
    {
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

                $filename = $this->getSplitSitemapFilename($type, $fileCount, $itemCount, $maxUrlsPerFile);
                $io = $this->openSitemapFile($filename);
                $this->_sitemapFiles[] = [
                    'filename' => $filename,
                    'lastmod' => Mage::getSingleton('core/date')->gmtDate(Mage_Core_Model_Locale::DATE_FORMAT),
                ];
            }

            // Write URL to sitemap
            $xml = $this->getSitemapRow($baseUrl . $item->getUrl(), $lastmod, $changefreq, $priority);
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
     * Get sitemap filename for a content type
     */
    protected function getSplitSitemapFilename(string $type, int $fileNumber, int $totalItems, int $maxUrlsPerFile): string
    {
        $baseName = pathinfo($this->getSitemapFilename(), PATHINFO_FILENAME);

        // If only one file is needed, use simple naming
        if ($totalItems <= $maxUrlsPerFile) {
            return $baseName . '-' . $type . '.xml';
        }

        // Multiple files needed, add number
        return $baseName . '-' . $type . '-' . $fileNumber . '.xml';
    }

    /**
     * Open and initialize a sitemap file
     */
    protected function openSitemapFile(string $filename): Varien_Io_File
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
        $io->streamWrite('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");

        return $io;
    }

    /**
     * Generate sitemap index file
     */
    protected function generateSitemapIndex(int $storeId, string $baseUrl, string $date): void
    {
        if (empty($this->_sitemapFiles)) {
            return;
        }

        $io = new Varien_Io_File();
        $io->setAllowCreateFolders(true);

        // Files should be saved in public directory for web accessibility
        $resolvedPath = Mage::getBaseDir('public');

        $io->open(['path' => $resolvedPath]);

        $indexFilename = $this->getSitemapFilename();
        if ($io->fileExists($indexFilename) && !$io->isWriteable($indexFilename)) {
            Mage::throwException(Mage::helper('sitemap')->__('File "%s" cannot be saved. Please, make sure the directory "%s" is writeable by web server.', $indexFilename, $resolvedPath));
        }

        $io->streamOpen($indexFilename);
        $io->streamWrite('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        $io->streamWrite('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");

        foreach ($this->_sitemapFiles as $sitemapFile) {
            $io->streamWrite('<sitemap>' . "\n");
            $io->streamWrite('<loc>' . htmlspecialchars($baseUrl . $this->getSitemapPath() . $sitemapFile['filename']) . '</loc>' . "\n");
            $io->streamWrite('<lastmod>' . $sitemapFile['lastmod'] . '</lastmod>' . "\n");
            $io->streamWrite('</sitemap>' . "\n");
        }

        $io->streamWrite('</sitemapindex>');
        $io->streamClose();
    }

    /**
     * Add sitemap file to the index (for external modules like blog)
     */
    public function addSitemapFile(string $filename, ?string $lastmod = null): void
    {
        if (!$lastmod) {
            $lastmod = Mage::getSingleton('core/date')->gmtDate(Mage_Core_Model_Locale::DATE_FORMAT);
        }

        $this->_sitemapFiles[] = [
            'filename' => $filename,
            'lastmod' => $lastmod,
        ];
    }
}
