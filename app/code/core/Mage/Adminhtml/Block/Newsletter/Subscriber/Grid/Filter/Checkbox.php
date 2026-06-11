<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Newsletter_Subscriber_Grid_Filter_Checkbox extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
{
    #[\Override]
    public function getCondition()
    {
        return [];
    }

    #[\Override]
    public function getHtml()
    {
        return '<input type="checkbox" onclick="subscriberController.checkCheckboxes(this)">';
    }
}
