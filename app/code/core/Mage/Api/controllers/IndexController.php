<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Api_IndexController extends Mage_Api_Controller_Action
{
    public function indexAction(): void
    {
        $this->_getServer()->init($this)->run();
    }
}
