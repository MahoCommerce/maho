<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block to render country_id attribute
 */
class Mage_Eav_Block_Widget_Form_Element_Country extends Mage_Eav_Block_Widget_Form_Element_Abstract
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setFieldId('country');
        $this->setTemplate('eav/widget/form/element/country.phtml');
    }

    public function getCountryId(): string
    {
        if ($countryId = $this->getObject()->getCountryId()) {
            return $countryId;
        }
        return Mage::helper('core')->getDefaultCountry();
    }

    public function getCountryHtmlSelect(): string
    {
        $block = $this->getLayout()->createBlock('directory/data');
        $block->setTranslationHelper($this->getTranslationHelper());

        return $block->getCountryHtmlSelect(
            $this->getCountryId(),
            $this->getFieldName(),
            $this->getFieldId(),
            $this->getLabel()
        );
    }
}
