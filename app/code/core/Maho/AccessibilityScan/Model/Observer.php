<?php

/**
 * Enables template hints in-memory for requests carrying a valid scan cookie.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_Observer
{
    /**
     * When the scanner browser presents a valid one-time scan token, force-enable
     * template hints for this request only (in-memory config, no DB writes) so the
     * rendered HTML carries the template source markers used for violation mapping.
     */
    #[Maho\Config\Observer('controller_action_predispatch', area: 'frontend', type: 'singleton', id: 'accessibilityscan_template_hints')]
    public function enableTemplateHints(\Maho\Event\Observer $observer): void
    {
        $token = (string) Mage::app()->getRequest()->getCookie(Maho_AccessibilityScan_Model_Runner::COOKIE_NAME, '');
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return;
        }
        $cacheKey = Maho_AccessibilityScan_Model_Runner::CACHE_KEY_PREFIX . $token;
        if (!Mage::app()->loadCache($cacheKey)) {
            return;
        }
        // Consume the token on first use: the scanner performs exactly one
        // page load per token, so a captured cookie (e.g. sniffed on a plain
        // http store) cannot be replayed to re-enable template hints later.
        // The runner's finally-block cleanup remains as a backstop.
        Mage::app()->removeCache($cacheKey);

        $store = Mage::app()->getStore();
        $store->setConfig(Mage_Core_Block_Template::XML_PATH_DEBUG_TEMPLATE_HINTS, '1');
        // Template hints also honor isDevAllowed(); lift the IP restriction for this request
        $store->setConfig(Mage_Core_Helper_Data::XML_PATH_DEV_ALLOW_IPS, '');
    }
}
