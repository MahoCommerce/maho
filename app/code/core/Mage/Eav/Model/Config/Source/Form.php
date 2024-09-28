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
 * Used in creating options for use_in_forms selection
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Model_Config_Source_Form
{
    protected const XML_PATH_EAV_FORMS = 'global/eav_forms';

    public function toOptionArray(string $entity = ''): array
    {
        $node = Mage::getConfig()->getNode(self::XML_PATH_EAV_FORMS . '/' . $entity);
        if ($node === false) {
            return [];
        }
        foreach ($node->children() as $form) {
            $moduleName = $form->getAttribute('module') ?? 'eav';
            $translatedLabel = Mage::helper($moduleName)->__((string)$form->label[0]);
            $forms[] = [
                'label' => $translatedLabel,
                'value' => $form->getName()
            ];
        }
        return $forms;
    }

    public function toOptionHash(string $entity = ''): array
    {
        $optionArray = $this->toOptionArray($entity);
        return array_combine(
            array_column($optionArray, 'value'),
            array_column($optionArray, 'label')
        );
    }
}
