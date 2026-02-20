<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Email Sequence Model
 *
 * Error Handling Pattern:
 * - Getter methods (getTemplate, getCouponSalesRule, getSegment): Return null if not found, log warning
 * - Validation methods (validate): Throw Mage_Core_Exception with user-friendly message
 * - Boolean checks (shouldGenerateCoupon): Return false on failure, never throw
 *
 * @method int getSegmentId()
 * @method string getTriggerEvent()
 * @method int getTemplateId()
 * @method int getStepNumber()
 * @method int getDelayMinutes()
 * @method bool getIsActive()
 * @method int getMaxSends()
 * @method bool getGenerateCoupon()
 * @method int|null getCouponSalesRuleId()
 * @method string|null getCouponPrefix()
 * @method int getCouponExpiresDays()
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 * @method Maho_CustomerSegmentation_Model_EmailSequence setSegmentId(int $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setTriggerEvent(string $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setTemplateId(int $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setStepNumber(int $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setDelayMinutes(int $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setIsActive(bool $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setMaxSends(int $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setGenerateCoupon(bool $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setCouponSalesRuleId(int|null $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setCouponPrefix(string|null $value)
 * @method Maho_CustomerSegmentation_Model_EmailSequence setCouponExpiresDays(int $value)
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

        // Convert empty string to NULL for coupon_sales_rule_id (prevents FK constraint violation)
        if ($this->getCouponSalesRuleId() === '' || $this->getCouponSalesRuleId() === 0) {
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
                $this->setIsActive(true);
            }
            if (!$this->hasData('max_sends')) {
                $this->setMaxSends(1);
            }
            if (!$this->hasData('delay_minutes')) {
                $this->setDelayMinutes(0);
            }
            if (!$this->hasData('generate_coupon')) {
                $this->setGenerateCoupon(false);
            }
            if (!$this->hasData('coupon_expires_days')) {
                $this->setCouponExpiresDays(30);
            }
        }

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
}
