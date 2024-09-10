<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Review status
 *
 * @category   Mage
 * @package    Mage_Review
 */
class Mage_Review_Model_Review_Status extends Mage_Core_Model_Abstract
{
    public function __construct()
    {
        $this->_init('review/review_status');
    }
}
