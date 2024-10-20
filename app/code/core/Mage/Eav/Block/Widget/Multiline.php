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
 * Block to render multiline attribute
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Widget_Multiline extends Mage_Eav_Block_Widget_Abstract
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/multiline.phtml');
    }

    public function getFields(): array
    {
        $attribute = $this->getAttribute();
        $code = $attribute->getAttributeCode();
        $values = explode("\n", $this->getObject()->getData($code));

        $fields = [];
        for ($i = 1; $i <= $attribute->getMultilineCount(); $i++) {
            $field = new Varien_Object();
            $field->setFieldId($this->getFieldId($code) . "_$i");
            $field->setFieldName($this->getFieldName($code) . '[]');
            $field->setValue($values[$i-1] ?? null);

            if ($i === 1) {
                $field->setStoreLabel($attribute->getStoreLabel());
                $field->setClass($attribute->getFrontend()->getClass());
                $field->setIsRequired($this->isRequired());
            } else {
                $field->setStoreLabel($attribute->getStoreLabel() . ' %s');
                $field->setClass(trim(str_replace('required-entry', '', $attribute->getFrontend()->getClass())));
                $field->setIsRequired(false);
            }
            $fields[$i] = $field;
        }
        return $fields;
    }
}
