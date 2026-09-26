<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

class Mage_Sitemap_Model_Observer
{
    /**
     * Enable/disable configuration
     */
    public const XML_PATH_GENERATION_ENABLED = 'sitemap/generate/enabled';

    /**
     * Cronjob expression configuration
     */
    public const XML_PATH_CRON_EXPR = 'crontab/jobs/sitemap_generate/schedule/cron_expr';

    /**
     * Error email template configuration
     */
    public const XML_PATH_ERROR_TEMPLATE  = 'sitemap/generate/error_email_template';

    /**
     * Error email identity configuration
     */
    public const XML_PATH_ERROR_IDENTITY  = 'sitemap/generate/error_email_identity';

    /**
     * 'Send error emails to' configuration
     */
    public const XML_PATH_ERROR_RECIPIENT = 'sitemap/generate/error_email';

    /**
     * Serve a sitemap file from a remote sitemaps mount. The web server serves a file on the
     * local disk directly, so only a file that is not on this disk reaches the no-route action.
     */
    #[Maho\Config\Observer('controller_action_predispatch', area: 'frontend')]
    public function serveStoredSitemap(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Core_Controller_Varien_Action $action */
        $action = $observer->getEvent()->getControllerAction();
        $request = $action->getRequest();
        if (strtolower((string) $request->getActionName()) !== 'noroute'
            || !str_ends_with(strtolower($request->getPathInfo()), '.xml')
        ) {
            return;
        }

        $path = Mage::helper('sitemap')->getStoredFilePath($request->getPathInfo(), (int) Mage::app()->getStore()->getId());
        $mount = Mage::getStorage('sitemaps');
        if ($path === null || !$mount->fileExists($path)) {
            return;
        }

        $action->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-Type', 'application/xml; charset=UTF-8', true)
            ->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
            ->setBody($mount->read($path));
        $action->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
    }

    /**
     * Generate sitemaps
     *
     * @param Mage_Cron_Model_Schedule $schedule
     */
    #[Maho\Config\CronJob('sitemap_generate', configPath: 'crontab/jobs/sitemap_generate/schedule/cron_expr')]
    public function scheduledGenerateSitemaps($schedule)
    {
        $errors = [];

        // check if scheduled generation enabled
        if (!Mage::getStoreConfigFlag(self::XML_PATH_GENERATION_ENABLED)) {
            return;
        }

        $collection = Mage::getModel('sitemap/sitemap')->getCollection();
        /** @var Mage_Sitemap_Model_Resource_Sitemap_Collection $collection */
        foreach ($collection as $sitemap) {
            /** @var Mage_Sitemap_Model_Sitemap $sitemap */

            try {
                $sitemap->generateXml();
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors && Mage::getStoreConfig(self::XML_PATH_ERROR_RECIPIENT)) {
            $emailTemplate = Mage::getModel('core/email_template');
            /** @var Mage_Core_Model_Email_Template $emailTemplate */
            $emailTemplate->setDesignConfig(['area' => 'backend'])
                ->sendTransactional(
                    Mage::getStoreConfig(self::XML_PATH_ERROR_TEMPLATE),
                    Mage::getStoreConfig(self::XML_PATH_ERROR_IDENTITY),
                    Mage::getStoreConfig(self::XML_PATH_ERROR_RECIPIENT),
                    null,
                    ['warnings' => implode("\n", $errors)],
                );
        }
    }
}
