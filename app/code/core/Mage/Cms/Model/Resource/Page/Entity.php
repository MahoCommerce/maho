<?php

/**
 * The EAV entity of the cms_page type, which the EAV config needs to load the custom fields of CMS pages.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

/**
 * It does not load or save pages: Mage_Cms_Model_Resource_Page does.
 */
class Mage_Cms_Model_Resource_Page_Entity extends Mage_Eav_Model_Entity_Abstract
{
    public function __construct()
    {
        $resource = Mage::getSingleton('core/resource');
        $this->setType(Mage_Cms_Model_Page::ENTITY);
        $this->setConnection($resource->getConnection('core_read'), $resource->getConnection('core_write'));
    }
}
