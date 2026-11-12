<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Service_Scan
{
    /**
     * Create a pending scan of $url. The store comes from the URL: the scanner renders what the URL serves.
     * An empty $wcagLevel takes the configured default level.
     *
     * @throws Mage_Core_Exception_Input when the URL is not valid or does not belong to a store base URL
     */
    public function start(string $url, ?string $wcagLevel, string $triggeredBy): Maho_AccessibilityScan_Model_Scan
    {
        $helper = Mage::helper('accessibilityscan');
        if ($url === '' || !Mage::helper('core')->isValidUrl($url)) {
            throw new Mage_Core_Exception_Input()->addError('url', $helper->__('Please provide a valid URL to scan'));
        }
        $storeId = $helper->resolveScanUrlStoreId($url);
        if ($storeId === null) {
            throw new Mage_Core_Exception_Input()
                ->addError('url', $helper->__('The URL to scan must belong to one of the configured store base URLs'));
        }

        $scan = Mage::getModel('accessibilityscan/scan');
        $scan->setUrl($url)
            ->setStoreId($storeId)
            ->setWcagLevel(trim((string) $wcagLevel) === '' ? $helper->getDefaultWcagLevel() : $helper->normalizeWcagLevel($wcagLevel))
            ->setTriggeredBy($triggeredBy)
            ->setStatus(Maho_AccessibilityScan_Model_Scan::STATUS_PENDING)
            ->save();
        return $scan;
    }
}
