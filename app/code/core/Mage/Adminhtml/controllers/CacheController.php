<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
    #[Maho\Config\Route('/admin/cache/index')]
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
    #[Maho\Config\Route('/admin/cache/flushAll')]
    public function flushAllAction(): void
    {
        $this->cacheService()->flush();
        $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('The cache storage has been flushed.'));
        $this->_redirect('*/*');
    }

    /**
     * Flush all Maho caches
     */
    #[Maho\Config\Route('/admin/cache/flushSystem')]
    public function flushSystemAction(): void
    {
        $this->cacheService()->flushSystem();
        $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('The Maho cache has been flushed and updates applied.'));
        $this->_redirect('*/*');
    }

    /**
     * Mass action for cache enabling
     */
    #[Maho\Config\Route('/admin/cache/massEnable')]
    public function massEnableAction(): void
    {
        $this->massAction(
            fn(array $codes): int => $this->cacheService()->enable($codes),
            fn(int $count): string => Mage::helper('adminhtml')->__('%s cache type(s) enabled.', $count),
        );
    }

    /**
     * Mass action for cache disabling
     */
    #[Maho\Config\Route('/admin/cache/massDisable')]
    public function massDisableAction(): void
    {
        $this->massAction(
            fn(array $codes): int => $this->cacheService()->disable($codes),
            fn(int $count): string => Mage::helper('adminhtml')->__('%s cache type(s) disabled.', $count),
        );
    }

    /**
     * Mass action for cache refresh
     */
    #[Maho\Config\Route('/admin/cache/massRefresh')]
    public function massRefreshAction(): void
    {
        $this->massAction(
            fn(array $codes): int => $this->cacheService()->refresh($codes),
            fn(int $count): string => Mage::helper('adminhtml')->__('%s cache type(s) refreshed.', $count),
        );
    }

    /**
     * Recompile PHP attributes (observers, cron jobs, routes, API permissions)
     * by shelling out to `composer dump-autoload`.
     */
    #[Maho\Config\Route('/admin/cache/recompileAttributes')]
    public function recompileAttributesAction(): void
    {
        [$ok, $message] = Maho::recompilePhpAttributes();
        if ($ok) {
            $this->_getSession()->addSuccess(
                Mage::helper('adminhtml')->__('PHP attributes were recompiled.'),
            );
        } else {
            $this->_getSession()->addError(
                Mage::helper('adminhtml')->__('PHP attribute recompilation failed: %s', $message),
            );
        }
        $this->_redirect('*/*');
    }

    /**
     * Clean catalog files cache
     */
    #[Maho\Config\Route('/admin/cache/cleanImages')]
    public function cleanImagesAction(): void
    {
        try {
            Mage::getSingleton('core/image_resizer')->refreshAll();
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
    #[Maho\Config\Route('/admin/cache/cleanSwatches')]
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

    #[Maho\Config\Route('/admin/cache/cleanMinifiedFiles')]
    public function cleanMinifiedFilesAction(): void
    {
        try {
            $this->cacheService()->flushMinified();
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

    /**
     * Run $action on the cache types of the grid selection, and show the message of $success for the number of changed types.
     *
     * @param callable(list<string>): int $action
     * @param callable(int): string $success
     */
    protected function massAction(callable $action, callable $success): void
    {
        $codes = $this->getRequest()->getParam('types');
        try {
            $count = $action(is_array($codes) ? array_values(array_map(strval(...), $codes)) : []);
            if ($count > 0) {
                $this->_getSession()->addSuccess($success($count));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        $this->_redirect('*/*');
    }

    protected function cacheService(): Mage_Core_Service_Cache
    {
        return Mage::getService('core/cache');
    }
}
