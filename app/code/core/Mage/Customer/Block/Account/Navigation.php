<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Block_Account_Navigation extends Mage_Core_Block_Template
{
    /**
     * @var array
     */
    protected $_links = [];

    /**
     * @var false|string
     */
    protected $_activeLink = false;

    /**
     * @param string $name
     * @param string $path
     * @param string $label
     * @param array $urlParams
     * @return $this
     */
    public function addLink($name, $path, $label, $urlParams = [])
    {
        $this->_links[$name] = new \Maho\DataObject([
            'name' => $name,
            'path' => $path,
            'label' => $label,
            'url' => $this->getUrl($path, $urlParams),
        ]);
        return $this;
    }

    /**
     * Remove a link
     *
     * @param string $name Name of the link
     * @return $this
     */
    public function removeLink($name)
    {
        if (isset($this->_links[$name])) {
            unset($this->_links[$name]);
        }
        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setActive($path)
    {
        $this->_activeLink = $this->_completePath($path);
        return $this;
    }

    /**
     * @return array
     */
    public function getLinks()
    {
        return $this->_links;
    }

    /**
     * @param \Maho\DataObject $link
     * @return bool
     */
    public function isActive($link)
    {
        if (empty($this->_activeLink)) {
            $this->_activeLink = $this->getAction()->getFullActionName('/');
        }
        if ($this->_completePath($link->getPath()) == $this->_activeLink) {
            return true;
        }
        return false;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function _completePath($path)
    {
        $path = rtrim($path, '/');
        switch (count(explode('/', $path))) {
            case 1:
                $path .= '/index';
                // no break

            case 2:
                $path .= '/index';
        }
        return $path;
    }

    /**
     * Add recurring profiles link only if customer has any profiles
     */
    public function addRecurringProfilesLink(): self
    {
        if (Mage::helper('sales')->customerHasRecurringProfiles()) {
            $this->addLink('recurring_profiles', 'sales/recurring_profile/', Mage::helper('sales')->__('Recurring Profiles'));
        }
        return $this;
    }

    /**
     * Add downloadable products link only if customer has any purchased downloads
     */
    public function addDownloadableProductsLink(): self
    {
        if (Mage::helper('downloadable')->customerHasDownloadableProducts()) {
            $this->addLink('downloadable_products', 'downloadable/customer/products', Mage::helper('downloadable')->__('My Downloadable Products'));
        }
        return $this;
    }

    /**
     * Add billing agreements link only if customer has any agreements
     */
    public function addBillingAgreementsLink(): self
    {
        if (Mage::helper('sales')->customerHasBillingAgreements()) {
            $this->addLink('billing_agreements', 'sales/billing_agreement/', Mage::helper('sales')->__('Billing Agreements'));
        }
        return $this;
    }

    /**
     * Add reviews link only if customer has any product reviews
     */
    public function addReviewsLink(): self
    {
        if (Mage::helper('review')->customerHasReviews()) {
            $this->addLink('reviews', 'review/customer', Mage::helper('review')->__('My Product Reviews'));
        }
        return $this;
    }
}
