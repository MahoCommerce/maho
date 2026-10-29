<?php

/**
 * Ends the API sessions of a user whose access a save takes away.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

/**
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api_Model_Observer
{
    /**
     * Mage_Api_Model_User and Maho_ApiPlatform_Model_User both save the api_user table with this event.
     * Only the event before the save holds the plain API key, which isAccessRevoked() needs.
     */
    #[Maho\Config\Observer('api_user_save_before')]
    public function clearSessionsOfRevokedUser(\Maho\Event\Observer $observer): void
    {
        $user = $observer->getEvent()->getDataObject();
        if (!$user instanceof Mage_Core_Model_Abstract || !$user->getId() || !$this->isAccessRevoked($user)) {
            return;
        }

        Mage::getResourceSingleton('api/user')->clearByUserId((int) $user->getId());
    }

    /**
     * True when the save deactivates the user or gives it a different API key.
     * The same key saved again, as the hash upgrade at login does, keeps the sessions.
     */
    protected function isAccessRevoked(Mage_Core_Model_Abstract $user): bool
    {
        $isActive = $user->getData('is_active');
        if ($isActive !== null && !(bool) $isActive) {
            return true;
        }

        $storedHash = (string) $user->getOrigData('api_key');
        $apiKey = (string) ($user->getData('new_api_key') ?: $user->getData('api_key'));
        if ($apiKey === '' || $apiKey === $storedHash) {
            return false;
        }

        return $storedHash === '' || !Mage::helper('core')->validateHash($apiKey, $storedHash);
    }
}
