<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_CacheController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/cache';

    /**
     * Retrieve session model
     *
     * @return Mage_Adminhtml_Model_Session
     */
    #[\Override]
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Display cache management grid
     */
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('Cache Management'));

        $this->loadLayout()
            ->_setActiveMenu('system/cache')
            ->renderLayout();
    }

    /**
     * Flush cache storage
     */
    public function flushAllAction(): void
    {
        Mage::app()->getCache()->flush();
        Mage::dispatchEvent('adminhtml_cache_flush_all');
        $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('The cache storage has been flushed.'));
        $this->_redirect('*/*');
    }

    /**
     * Flush all Maho caches
     */
    public function flushSystemAction(): void
    {
        Mage::app()->getCache()->banUse('config');
        Mage::getConfig()->reinit();
        Mage::getConfig()->getCacheSaveLock(30, true);
        try {
            Mage::app()->cleanCache();
            Mage_Core_Model_Resource_Setup::applyAllUpdates();
            Mage_Core_Model_Resource_Setup::applyAllDataUpdates();
            Mage_Core_Model_Resource_Setup::applyAllMahoUpdates();
            Mage::app()->getCache()->unbanUse('config');
            Mage::getConfig()->saveCache();
        } finally {
            Mage::getConfig()->releaseCacheSaveLock();
        }
        Mage::dispatchEvent('adminhtml_cache_flush_system');
        $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('The Maho cache has been flushed and updates applied.'));
        $this->_redirect('*/*');
    }

    /**
     * Mass action for cache enabling
     */
    public function massEnableAction(): void
    {
        $types = $this->getRequest()->getParam('types');
        $allTypes = Mage::app()->useCache();

        $updatedTypes = 0;
        foreach ($types as $code) {
            if (empty($allTypes[$code])) {
                $allTypes[$code] = 1;
                $updatedTypes++;
            }
        }
        if ($updatedTypes > 0) {
            Mage::app()->saveUseCache($allTypes);
            $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('%s cache type(s) enabled.', $updatedTypes));
        }
        $this->_redirect('*/*');
    }

    /**
     * Mass action for cache disabling
     */
    public function massDisableAction(): void
    {
        $types = $this->getRequest()->getParam('types');
        $allTypes = Mage::app()->useCache();

        $updatedTypes = 0;
        foreach ($types as $code) {
            if (!empty($allTypes[$code])) {
                $allTypes[$code] = 0;
                $updatedTypes++;
            }
            $tags = Mage::app()->getCache()->cleanType($code);
        }
        if ($updatedTypes > 0) {
            Mage::app()->saveUseCache($allTypes);
            $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('%s cache type(s) disabled.', $updatedTypes));
        }
        $this->_redirect('*/*');
    }

    /**
     * Mass action for cache refresh
     */
    public function massRefreshAction(): void
    {
        $types = $this->getRequest()->getParam('types');
        $updatedTypes = 0;
        if (!empty($types)) {
            foreach ($types as $type) {
                $tags = Mage::app()->getCache()->cleanType($type);
                Mage::dispatchEvent('adminhtml_cache_refresh_type', ['type' => $type]);
                $updatedTypes++;
            }
        }
        if ($updatedTypes > 0) {
            $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('%s cache type(s) refreshed.', $updatedTypes));
        }
        $this->_redirect('*/*');
    }

    /**
     * Clean catalog files cache
     */
    public function cleanImagesAction(): void
    {
        try {
            Mage::getModel('catalog/product_image')->clearCache();
            Mage::dispatchEvent('clean_catalog_images_cache_after');
            $this->_getSession()->addSuccess(
                Mage::helper('adminhtml')->__('The image cache was cleaned.'),
            );
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('adminhtml')->__('An error occurred while clearing the image cache.'),
            );
        }
        $this->_redirect('*/*');
    }

    /**
     * Clean configurable swatches files cache
     */
    public function cleanSwatchesAction(): void
    {
        try {
            Mage::helper('configurableswatches/productimg')->clearSwatchesCache();
            Mage::dispatchEvent('clean_configurable_swatches_cache_after');
            $this->_getSession()->addSuccess(
                Mage::helper('adminhtml')->__('The configurable swatches image cache was cleaned.'),
            );
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('adminhtml')->__('An error occurred while clearing the configurable swatches image cache.'),
            );
        }
        $this->_redirect('*/*');
    }

    public function cleanMinifiedFilesAction(): void
    {
        try {
            Mage::helper('core/minify')->clearCache();
            Mage::dispatchEvent('clean_minified_cache_after');
            $this->_getSession()->addSuccess(
                Mage::helper('adminhtml')->__('The minified CSS/JS cache was cleaned.'),
            );
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('adminhtml')->__('An error occurred while clearing the minified CSS/JS cache.'),
            );
        }
        $this->_redirect('*/*');
    }
}
