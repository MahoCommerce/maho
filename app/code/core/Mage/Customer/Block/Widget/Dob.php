<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * @package    Mage_Customer
 */
class Mage_Customer_Block_Widget_Dob extends Mage_Customer_Block_Widget_Abstract
{
    /**
     * Date inputs
     *
     * @var array
     */
    protected $_dateInputs = [];

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('customer/widget/dob.phtml');
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return (bool) $this->_getAttribute('dob')->getIsVisible();
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return (bool) $this->_getAttribute('dob')->getIsRequired();
    }

    /**
     * @param string $date
     * @return $this
     */
    public function setDate($date)
    {
        if ($date) {
            try {
                $dateTime = new DateTime($date);
                $this->setTime($dateTime);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        $this->setData('date', $date);

        return $this;
    }

    /**
     * @return bool
     */
    public function hasTime()
    {
        return ($this->getTime() instanceof DateTime);
    }

    /**
     * @return string
     */
    public function getDay()
    {
        return ($this->hasTime()) ? $this->getTime()->format('d') : '';
    }

    /**
     * @return string
     */
    public function getMonth()
    {
        return ($this->hasTime()) ? $this->getTime()->format('m') : '';
    }

    /**
     * @return string
     */
    public function getYear()
    {
        return ($this->hasTime()) ? $this->getTime()->format('Y') : '';
    }

    /**
     * Returns format which will be applied for DOB in javascript
     *
     * @return string
     */
    public function getDateFormat()
    {
        return Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
    }

    /**
     * Add date input html
     *
     * @param string $code
     * @param string $html
     */
    public function setDateInput($code, $html): void
    {
        $this->_dateInputs[$code] = $html;
    }

    /**
     * Return the day, month and year inputs in the order of the short date format of the locale, which is an ICU
     * pattern such as M/d/yy or dd.MM.y.
     *
     * @return string
     */
    public function getSortedDateInputs()
    {
        $format = (string) $this->getDateFormat();
        $positions = [];
        foreach (['m' => '/[ML]/', 'd' => '/d/', 'y' => '/[yY]/'] as $code => $pattern) {
            $positions[$code] = preg_match($pattern, $format, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : PHP_INT_MAX;
        }
        asort($positions);
        return implode('', array_map(fn(string $code) => $this->_dateInputs[$code], array_keys($positions)));
    }

    /**
     * Return the date as YYYY-MM-DD, the value that the form sends when the customer does not change the date.
     */
    public function getIsoDate(): string
    {
        return $this->hasTime() ? $this->getTime()->format(Mage_Core_Model_Locale::DATE_FORMAT) : '';
    }

    public function getTime(): ?DateTime
    {
        return $this->getData('time');
    }

    public function setTime(?DateTime $value): static
    {
        return $this->setData('time', $value);
    }
}
