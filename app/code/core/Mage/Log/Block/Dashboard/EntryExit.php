<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Entry and exit pages block for dashboard
 */
class Mage_Log_Block_Dashboard_EntryExit extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/entry_exit.phtml');
    }

    /**
     * Get entry (landing) pages
     */
    public function getEntryPages(): array
    {
        return Mage::helper('log/dashboard')->getEntryPages(7, 10);
    }

    /**
     * Get exit pages
     */
    public function getExitPages(): array
    {
        return Mage::helper('log/dashboard')->getExitPages(7, 10);
    }

    /**
     * Get URL path for display
     */
    public function getDisplayPath(string $url): string
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';

        // If it's already a friendly URL (not an internal route), return as-is
        if (!str_contains($path, '/catalog/') && !str_contains($path, '/customer/')) {
            return $path ?: '/';
        }

        // For old data with internal URLs, try to find the friendly version
        $internalPath = ltrim($path, '/');
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), ['request_path'])
            ->where('target_path = ?', $internalPath)
            ->where('store_id > 0')
            ->limit(1);

        $friendlyPath = $adapter->fetchOne($select);

        return $friendlyPath ? '/' . $friendlyPath : $path;
    }
}
