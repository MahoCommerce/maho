<?php

/**
 * Reads the entity type of System > Attributes from the entity_type request parameter.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * The attribute model of the type gives the ACL resource, the titles and the properties tab through
 * Mage_Eav_Model_Entity_Attribute::getAdminPageSettings(). A type without settings is refused.
 */
trait Mage_Adminhtml_Controller_Eav_EntityTypeTrait
{
    protected Mage_Eav_Model_Entity_Attribute_AdminPageSettings|false|null $adminPageSettings = null;

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return (string) $this->getRequest()->getParam('entity_type');
    }

    /**
     * Return the settings of the requested entity type, or null when System > Attributes does not manage it.
     */
    protected function getAdminPageSettings(): ?Mage_Eav_Model_Entity_Attribute_AdminPageSettings
    {
        if ($this->adminPageSettings === null) {
            $this->adminPageSettings = false;
            $code = $this->getEntityTypeCode();
            try {
                $type = Mage::getSingleton('eav/config')->getEntityType($code);
                // getEntityType() also finds a type by its id, and the urls must name the code
                if ($type->getEntityTypeCode() === $code) {
                    $this->adminPageSettings = Mage::helper('eav')->getAdminPageSettings($type) ?? false;
                }
            } catch (Mage_Core_Exception) {
                // An unknown entity type has no settings
            }
        }
        return $this->adminPageSettings ?: null;
    }

    /**
     * Return the settings of the requested entity type. _isAllowed() refuses a type without settings.
     */
    protected function getPageSettings(): Mage_Eav_Model_Entity_Attribute_AdminPageSettings
    {
        return $this->getAdminPageSettings() ?? throw new Mage_Core_Exception('System > Attributes does not manage this entity type.');
    }

    #[\Override]
    protected function _isAllowed()
    {
        return $this->isEntityTypeAllowed();
    }

    protected function isEntityTypeAllowed(): bool
    {
        $settings = $this->getAdminPageSettings();
        return $settings !== null && Mage::getSingleton('admin/session')->isAllowed($settings->acl);
    }

    #[\Override]
    protected function getActiveMenu(): string
    {
        return $this->getPageSettings()->acl;
    }

    /**
     * Keep the entity type in every url and redirect of the page.
     */
    #[\Override]
    public function getUrl($route = '', $params = [])
    {
        return parent::getUrl($route, ['entity_type' => $this->getEntityTypeCode()] + $params);
    }
}
