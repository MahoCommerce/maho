<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_JsonController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Return JSON-encoded array of country regions
     */
    #[Maho\Config\Route('/admin/json/countryRegion')]
    public function countryRegionAction(): void
    {
        $arrRes = [];

        $countryId = $this->getRequest()->getParam('parent');
        $arrRegions = Mage::getResourceModel('directory/region_collection')
            ->addCountryFilter($countryId)
            ->load()
            ->toOptionArray();

        if (!empty($arrRegions)) {
            foreach ($arrRegions as $region) {
                $arrRes[] = $region;
            }
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($arrRes));
    }

    /**
     * Allow access to all admin users
     *
     * This endpoint serves read-only reference data (country/region lists)
     * required by various admin forms. No ACL restriction is needed.
     *
     * @return true
     */
    #[\Override]
    protected function _isAllowed()
    {
        return true;
    }
}
