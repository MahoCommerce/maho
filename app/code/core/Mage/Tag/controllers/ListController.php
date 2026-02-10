<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Tag
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Tag_ListController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}
