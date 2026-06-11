<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

/**
 * Insertable revocation link (EU Directive 2023/2673). Exposed as a CMS widget so the
 * merchant can place it wherever their (usually custom) footer or layout requires.
 * Renders only the link; the surrounding markup belongs to whoever places it.
 */
class Maho_Revocation_Block_Widget_Button extends Mage_Core_Block_Template implements Mage_Widget_Block_Interface
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('revocation/button.phtml');
    }

    public function getButtonLabel(): string
    {
        $custom = trim((string) $this->getData('label'));
        return $custom !== '' ? $custom : Mage::helper('revocation')->getButtonLabel();
    }

    public function getRevocationUrl(): string
    {
        return Mage::getUrl('revocation/index/index');
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (!Mage::helper('revocation')->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }
}
