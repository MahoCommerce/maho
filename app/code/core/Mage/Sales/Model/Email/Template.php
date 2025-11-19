<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Email_Template extends Mage_Core_Model_Email_Template
{
    /**
     * @param string $template
     * @return false|string
     */
    #[\Override]
    public function getInclude($template, array $variables)
    {
        $filename = Mage::getDesign()->getTemplateFilename($template);
        if (!$filename) {
            return '';
        }
        extract($variables, EXTR_SKIP);
        ob_start();
        include $filename;
        return ob_get_clean();
    }
}
