<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method getTitle
 * @method getContent
 */

class Maho_Blog_Model_Post extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('blog/post');
    }
}
