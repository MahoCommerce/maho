<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Model_Resource_Template_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Define resource model and model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('newsletter/template');
    }

    /**
     * Load only actual template
     *
     * @return $this
     */
    public function useOnlyActual()
    {
        $this->addFieldToFilter('template_actual', 1);

        return $this;
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('template_id', 'template_code');
    }
}
