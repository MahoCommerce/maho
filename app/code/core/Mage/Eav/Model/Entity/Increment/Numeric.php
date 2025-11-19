<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Entity_Increment_Numeric extends Mage_Eav_Model_Entity_Increment_Abstract
{
    /**
     * @return string
     */
    #[\Override]
    public function getNextId()
    {
        $last = $this->getLastId();

        if (empty($last)) {
            $last = 0;
        } elseif (!empty($prefix = (string) $this->getPrefix()) && str_starts_with($last, $prefix)) {
            $last = (int) substr($last, strlen($prefix));
        } else {
            $last = (int) $last;
        }

        $next = $last + 1;

        return $this->format($next);
    }
}
