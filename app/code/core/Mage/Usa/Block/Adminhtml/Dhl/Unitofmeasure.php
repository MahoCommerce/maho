<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Usa_Block_Adminhtml_Dhl_Unitofmeasure extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();

        $carrierModel = Mage::getSingleton('usa/shipping_carrier_dhl_international');

        $this->setInch($this->jsQuoteEscape($carrierModel->getCode('unit_of_dimension_cut', 'I')));
        $this->setCm($this->jsQuoteEscape($carrierModel->getCode('unit_of_dimension_cut', 'C')));

        $this->setHeight($this->jsQuoteEscape($carrierModel->getCode('dimensions', 'height')));
        $this->setDepth($this->jsQuoteEscape($carrierModel->getCode('dimensions', 'depth')));
        $this->setWidth($this->jsQuoteEscape($carrierModel->getCode('dimensions', 'width')));

        $kgWeight = 70;

        $this->setDivideOrderWeightNoteKg(
            $this->jsQuoteEscape($this->__('Allows breaking total order weight into smaller pieces if it exeeds %s %s to ensure accurate calculation of shipping charges.', $kgWeight, 'kg')),
        );

        $weight = round(
            (float) Mage::helper('usa')->convertMeasureWeight(
                $kgWeight,
                Mage_Core_Model_Locale::WEIGHT_KILOGRAM,
                Mage_Core_Model_Locale::WEIGHT_POUND,
            ),
            3,
        );

        $this->setDivideOrderWeightNoteLbp(
            $this->jsQuoteEscape($this->__('Allows breaking total order weight into smaller pieces if it exeeds %s %s to ensure accurate calculation of shipping charges.', $weight, 'pounds')),
        );

        $this->setTemplate('usa/dhl/unitofmeasure.phtml');
    }

    /**
     * Retrieve Element HTML fragment
     *
     * @return string
     */
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        return parent::_getElementHtml($element) . $this->renderView();
    }
}
