<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

/**
 * Email Sequence Model
 *
 * Error Handling Pattern:
 * - Getter methods (getTemplate, getCouponSalesRule, getSegment): Return null if not found, log warning
 * - Validation methods (validate): Throw Mage_Core_Exception with user-friendly message
 * - Boolean checks (shouldGenerateCoupon): Return false on failure, never throw
 */
class Maho_CustomerSegmentation_Model_EmailSequence extends Mage_Core_Model_Abstract
{
    public const TRIGGER_ENTER = 'enter';
    public const TRIGGER_EXIT = 'exit';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customersegmentation/emailSequence');
        $this->setIdFieldName('sequence_id');
    }

    /**
     * Get newsletter template for this sequence step
     *
     * @return Mage_Newsletter_Model_Template|null Returns null if no template ID is set
     */
    public function getTemplate(): ?Mage_Newsletter_Model_Template
    {
        if ($this->getTemplateId()) {
            $template = Mage::getModel('newsletter/template')->load($this->getTemplateId());
            if (!$template->getId()) {
                Mage::log(
                    "Newsletter template {$this->getTemplateId()} not found for sequence {$this->getId()}",
                    Mage::LOG_WARNING,
                );
                return null;
            }
            return $template;
        }
        return null;
    }

    /**
     * Get delay in human readable format
     */
    public function getDelayFormatted(): string
    {
        $minutes = $this->getDelayMinutes();
        if ($minutes < 60) {
            return $minutes . ' ' . Mage::helper('customersegmentation')->__('minutes');
        }
        if ($minutes < 1440) {
            $hours = round($minutes / 60);
            return $hours . ' ' . Mage::helper('customersegmentation')->__('hours');
        }
        $days = round($minutes / 1440);
        return $days . ' ' . Mage::helper('customersegmentation')->__('days');
    }

    /**
     * Check if this sequence step should generate coupons
     */
    public function shouldGenerateCoupon(): bool
    {
        return (bool) $this->getGenerateCoupon() && $this->getCouponSalesRuleId();
    }

    /**
     * Get the base sales rule for coupon generation
     *
     * @return Mage_SalesRule_Model_Rule|null Returns null if no sales rule ID is set
     */
    public function getCouponSalesRule(): ?Mage_SalesRule_Model_Rule
    {
        if ($this->getCouponSalesRuleId()) {
            $rule = Mage::getModel('salesrule/rule')->load($this->getCouponSalesRuleId());
            if (!$rule->getId()) {
                Mage::log(
                    "Sales rule {$this->getCouponSalesRuleId()} not found for sequence {$this->getId()}",
                    Mage::LOG_WARNING,
                );
                return null;
            }
            return $rule;
        }
        return null;
    }

    /**
     * Get segment model
     */
    public function getSegment(): ?Maho_CustomerSegmentation_Model_Segment
    {
        if ($this->getSegmentId()) {
            return Mage::getModel('customersegmentation/segment')->load($this->getSegmentId());
        }
        return null;
    }

    /**
     * Validate sequence data before save
     */
    public function validate(): bool
    {
        $errors = [];

        if (!$this->getSegmentId()) {
            $errors[] = Mage::helper('customersegmentation')->__('Segment ID is required.');
        }

        if (!$this->getTriggerEvent() || !in_array($this->getTriggerEvent(), [self::TRIGGER_ENTER, self::TRIGGER_EXIT])) {
            $errors[] = Mage::helper('customersegmentation')->__('Trigger event must be either "enter" or "exit".');
        }

        if (!$this->getTemplateId()) {
            $errors[] = Mage::helper('customersegmentation')->__('Template ID is required.');
        }

        if (!$this->getStepNumber() || $this->getStepNumber() < 1) {
            $errors[] = Mage::helper('customersegmentation')->__('Step number must be greater than 0.');
        }

        if ($this->getDelayMinutes() < 0) {
            $errors[] = Mage::helper('customersegmentation')->__('Delay minutes cannot be negative.');
        }


        // Validate coupon settings if enabled
        if ($this->getGenerateCoupon()) {
            if (!$this->getCouponSalesRuleId()) {
                $errors[] = Mage::helper('customersegmentation')->__('Sales rule is required when coupon generation is enabled.');
            }

            if ($this->getCouponExpiresDays() < 0) {
                $errors[] = Mage::helper('customersegmentation')->__('Coupon expiration days cannot be negative.');
            }

            // Validate sales rule exists and is active
            $salesRule = $this->getCouponSalesRule();
            if (!$salesRule || !$salesRule->getId()) {
                $errors[] = Mage::helper('customersegmentation')->__('Invalid sales rule selected for coupon generation.');
            } else {
                if (!$salesRule->getIsActive()) {
                    $errors[] = Mage::helper('customersegmentation')->__('Selected sales rule is not active.');
                }

                // Validate sales rule is configured for auto-generation
                if ($salesRule->getCouponType() !== Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC) {
                    $errors[] = Mage::helper('customersegmentation')->__('Selected sales rule must be configured for "Specific Coupon" type.');
                }

                if (!$salesRule->getUseAutoGeneration()) {
                    $errors[] = Mage::helper('customersegmentation')->__('Selected sales rule must have "Use Auto Generation" enabled.');
                }
            }
        }

        // Validate template exists and is valid
        $template = $this->getTemplate();
        if (!$template || !$template->getId()) {
            $errors[] = Mage::helper('customersegmentation')->__('Invalid newsletter template selected.');
        }

        if (!empty($errors)) {
            Mage::throwException(implode("\n", $errors));
        }

        return true;
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        parent::_beforeSave();

        // Clean up coupon-related fields
        // If generate_coupon is disabled, clear coupon fields
        if (!$this->getGenerateCoupon()) {
            $this->setCouponSalesRuleId(null);
            $this->setCouponPrefix(null);
        }

        // Convert an empty selection to NULL for coupon_sales_rule_id (prevents FK constraint violation)
        if ($this->getCouponSalesRuleId() === 0) {
            $this->setCouponSalesRuleId(null);
        }

        // Validate data
        $this->validate();

        // Set default values for new sequences
        if ($this->isObjectNew()) {
            if (!$this->hasData('trigger_event')) {
                $this->setTriggerEvent(self::TRIGGER_ENTER);
            }
            if (!$this->hasData('is_active')) {
                $this->setIsActive(1);
            }
            if (!$this->hasData('max_sends')) {
                $this->setMaxSends(1);
            }
            if (!$this->hasData('delay_minutes')) {
                $this->setDelayMinutes(0);
            }
            if (!$this->hasData('generate_coupon')) {
                $this->setGenerateCoupon(0);
            }
            if (!$this->hasData('coupon_expires_days')) {
                $this->setCouponExpiresDays(30);
            }
        }

        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if ($this->isObjectNew() && !$this->getCreatedAt()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        return $this;
    }

    #[\Override]
    protected function _afterSave(): self
    {
        parent::_afterSave();

        // Clear any existing progress for this sequence if it was deactivated
        if (!$this->getIsActive() && $this->getOrigData('is_active')) {
            $this->_getResource()->clearSequenceProgress($this->getId());
        }

        return $this;
    }

    public function getSegmentId(): ?int
    {
        $value = $this->getData('segment_id');
        return $value === null ? null : (int) $value;
    }

    public function setSegmentId(?int $value): static
    {
        return $this->setData('segment_id', $value);
    }

    public function getTriggerEvent(): ?string
    {
        $value = $this->getData('trigger_event');
        return $value === null ? null : (string) $value;
    }

    public function setTriggerEvent(?string $value): static
    {
        return $this->setData('trigger_event', $value);
    }

    public function getTemplateId(): ?int
    {
        $value = $this->getData('template_id');
        return $value === null ? null : (int) $value;
    }

    public function setTemplateId(?int $value): static
    {
        return $this->setData('template_id', $value);
    }

    public function getStepNumber(): ?int
    {
        $value = $this->getData('step_number');
        return $value === null ? null : (int) $value;
    }

    public function setStepNumber(?int $value): static
    {
        return $this->setData('step_number', $value);
    }

    public function getDelayMinutes(): ?int
    {
        $value = $this->getData('delay_minutes');
        return $value === null ? null : (int) $value;
    }

    public function setDelayMinutes(?int $value): static
    {
        return $this->setData('delay_minutes', $value);
    }

    public function getIsActive(): ?int
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (int) $value;
    }

    public function setIsActive(?int $value): static
    {
        return $this->setData('is_active', $value);
    }

    public function getMaxSends(): ?int
    {
        $value = $this->getData('max_sends');
        return $value === null ? null : (int) $value;
    }

    public function setMaxSends(?int $value): static
    {
        return $this->setData('max_sends', $value);
    }

    public function getGenerateCoupon(): ?int
    {
        $value = $this->getData('generate_coupon');
        return $value === null ? null : (int) $value;
    }

    public function setGenerateCoupon(?int $value): static
    {
        return $this->setData('generate_coupon', $value);
    }

    public function getCouponSalesRuleId(): ?int
    {
        $value = $this->getData('coupon_sales_rule_id');
        return $value === null ? null : (int) $value;
    }

    public function setCouponSalesRuleId(?int $value): static
    {
        return $this->setData('coupon_sales_rule_id', $value);
    }

    public function getCouponPrefix(): ?string
    {
        $value = $this->getData('coupon_prefix');
        return $value === null ? null : (string) $value;
    }

    public function setCouponPrefix(?string $value): static
    {
        return $this->setData('coupon_prefix', $value);
    }

    public function getCouponExpiresDays(): ?int
    {
        $value = $this->getData('coupon_expires_days');
        return $value === null ? null : (int) $value;
    }

    public function setCouponExpiresDays(?int $value): static
    {
        return $this->setData('coupon_expires_days', $value);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }
}
