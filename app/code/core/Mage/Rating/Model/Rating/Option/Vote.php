<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Rating
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @method Mage_Rating_Model_Resource_Rating_Option_Vote_Collection getResourceCollection()
 * @method string getEntityPkValue()
 * @method int getRatingId()
 * @method $this setRatingOptions(Mage_Rating_Model_Resource_Rating_Option_Collection $options)
 */

class Mage_Rating_Model_Rating_Option_Vote extends Mage_Core_Model_Abstract
{
    public function __construct()
    {
        $this->_init('rating/rating_option_vote');
    }
}
