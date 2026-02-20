<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Import extends \Maho\Data\Form\Element\AbstractElement
{
    /**
     * @param array $data
     */
    public function __construct($data)
    {
        parent::__construct($data);
        $this->setType('file');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '';

        $html .= '<input id="time_condition" type="hidden" name="' . $this->getName() . '" value="' . time() . '" />';

        $html .= <<<EndHTML
        <script type="text/javascript">
        document.getElementById('carriers_tablerate_condition_name').addEventListener('change', checkConditionName);
        function checkConditionName(event)
        {
            var conditionNameElement = event.target;
            if (conditionNameElement && conditionNameElement.id) {
                document.getElementById('time_condition').value = '_' + conditionNameElement.value + '/' + Math.random();
            }
        }
        </script>
EndHTML;

        $html .= parent::getElementHtml();

        return $html;
    }
}
