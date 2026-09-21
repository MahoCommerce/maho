<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

/**
 * Convert history
 *
 * @package    Mage_Dataflow
 *
 * @method Mage_Dataflow_Model_Resource_Profile_History _getResource()
 * @method Mage_Dataflow_Model_Resource_Profile_History getResource()
 */
class Mage_Dataflow_Model_Profile_History extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/profile_history');
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getProfileId()) {
            $profile = Mage::registry('current_convert_profile');
            if ($profile) {
                $this->setProfileId($profile->getId());
            }
        }

        if (!$this->hasData('user_id')) {
            $this->setUserId(0);
        }

        parent::_beforeSave();
        return $this;
    }

    public function getActionCode(): ?string
    {
        $value = $this->getData('action_code');
        return $value === null ? null : (string) $value;
    }

    public function setActionCode(?string $value): static
    {
        return $this->setData('action_code', $value);
    }

    public function getPerformedAt(): ?string
    {
        $value = $this->getData('performed_at');
        return $value === null ? null : (string) $value;
    }

    public function setPerformedAt(?string $value): static
    {
        return $this->setData('performed_at', $value);
    }

    public function getProfileId(): ?int
    {
        $value = $this->getData('profile_id');
        return $value === null ? null : (int) $value;
    }

    public function setProfileId(?int $value): static
    {
        return $this->setData('profile_id', $value);
    }

    public function getUserId(): ?int
    {
        $value = $this->getData('user_id');
        return $value === null ? null : (int) $value;
    }

    public function setUserId(?int $value): static
    {
        return $this->setData('user_id', $value);
    }

}
