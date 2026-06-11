<?php

/**
 * Maho
 *
 * @package    Maho_Revocation
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
