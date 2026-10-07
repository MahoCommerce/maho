<?php

/**
 * Give an API provider or processor the interface locale of the admin user of the token.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

trait AdminLocaleTrait
{
    /**
     * Return the interface locale of the admin user of the token, as the web admin uses it.
     * A token without an admin user gets the default locale of the admin.
     */
    protected function adminLocale(): string
    {
        $adminId = $this->requireUser()->getAdminId();
        if ($adminId) {
            /** @var \Mage_Admin_Model_User $admin */
            $admin = \Mage::getModel('admin/user')->load($adminId);
            $locale = (string) $admin->getData('backend_locale');
            if ($this->isLocaleCode($locale)) {
                return $locale;
            }
        }

        $locale = (string) \Mage::getStoreConfig(\Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, \Mage_Core_Model_App::ADMIN_STORE_ID);
        return $this->isLocaleCode($locale) ? $locale : \Mage_Core_Model_Locale::DEFAULT_LOCALE;
    }

    private function isLocaleCode(string $locale): bool
    {
        return (bool) preg_match('/^[a-z]{2,3}(_[A-Za-z0-9]{2,8})*$/', $locale);
    }
}
