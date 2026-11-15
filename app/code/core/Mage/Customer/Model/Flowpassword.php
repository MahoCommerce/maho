<?php

/**
 * One request for a password reset email. The rows are the durable counter of the forgot password limit.
 *
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Mage_Customer_Model_Config_Forgotpassword as Flow;

/**
 * @method Mage_Customer_Model_Resource_Flowpassword_Collection getCollection()
 */
class Mage_Customer_Model_Flowpassword extends Mage_Core_Model_Abstract
{
    public const EMAIL_WINDOW = 86400;
    public const IP_WINDOW = 3600;

    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/flowpassword');
    }

    #[\Override]
    protected function _beforeSave()
    {
        $this->_prepareData();
        return parent::_beforeSave();
    }

    /**
     * Set the address of the client and the date of the request.
     *
     * @return $this
     */
    protected function _prepareData()
    {
        if ($this->getIp() === null) {
            $this->setIp((string) Mage::helper('core/http')->getRemoteAddr());
        }
        $this->setRequestedDate(Mage::app()->getLocale()->formatDateForDb('now'));
        return $this;
    }

    /**
     * Return false when $email has more requests in the last 24 hours than the store allows.
     * The request to check must be saved first, so that it counts.
     */
    public function checkCustomerForgotPasswordFlowEmail(#[\SensitiveParameter] string $email): bool
    {
        $helper = Mage::helper('customer');
        if (!in_array($helper->getCustomerForgotPasswordFlowSecure(), [Flow::FLOW_IP_EMAIL, Flow::FLOW_EMAIL], true)) {
            return true;
        }
        return $this->countRequests('email', $email, self::EMAIL_WINDOW) <= $helper->getCustomerForgotPasswordEmailTimes();
    }

    /**
     * Return false when the address of the client has more requests in the last hour than the store allows.
     * The request to check must be saved first, so that it counts.
     */
    public function checkCustomerForgotPasswordFlowIp(): bool
    {
        $helper = Mage::helper('customer');
        $ip = $this->getIp() ?? (string) Mage::helper('core/http')->getRemoteAddr();
        if ($ip === '' || !in_array($helper->getCustomerForgotPasswordFlowSecure(), [Flow::FLOW_IP_EMAIL, Flow::FLOW_IP], true)) {
            return true;
        }
        return $this->countRequests('ip', $ip, self::IP_WINDOW) <= $helper->getCustomerForgotPasswordIpTimes();
    }

    #[\Deprecated(message: 'Mage_Customer_Service_Authentication::sendMagicLink() counts the requests with the limit system/rate_limit/magic_link_email', since: '26.11')]
    public function checkMagicLinkFlowEmail(#[\SensitiveParameter] string $email): bool
    {
        return !Mage::helper('core')->rateLimiterBy(
            'magic_link',
            'magic_link:email:' . strtolower($email),
            (int) Mage::getStoreConfig(Mage_Customer_Service_Authentication::XML_PATH_MAGIC_LINK_EMAIL_RATE_LIMIT),
            Mage_Customer_Service_Authentication::RATE_LIMIT_WINDOW,
        )->tooManyAttempts();
    }

    #[\Deprecated(message: 'Mage_Customer_Service_Authentication::sendMagicLink() counts the requests with the limit system/rate_limit/magic_link_ip', since: '26.11')]
    public function checkMagicLinkFlowIp(): bool
    {
        return !Mage::helper('core')->rateLimiter(
            'magic_link',
            (int) Mage::getStoreConfig(Mage_Customer_Service_Authentication::XML_PATH_MAGIC_LINK_IP_RATE_LIMIT),
            Mage_Customer_Service_Authentication::RATE_LIMIT_WINDOW,
            \Maho\Security\RateLimitScope::Ip,
        )->tooManyAttempts();
    }

    /**
     * Count the requests of the last $seconds seconds whose $field is $value.
     */
    protected function countRequests(string $field, #[\SensitiveParameter] string $value, int $seconds): int
    {
        return $this->getCollection()
            ->addFieldToFilter($field, ['eq' => $value])
            ->addFieldToFilter('requested_date', ['gt' => Mage::app()->getLocale()->formatDateForDb("-{$seconds} seconds")])
            ->getSize();
    }

    public function getEmail(): ?string
    {
        $value = $this->getData('email');
        return $value === null ? null : (string) $value;
    }

    public function setEmail(#[\SensitiveParameter] ?string $value): static
    {
        return $this->setData('email', $value);
    }

    public function getIp(): ?string
    {
        $value = $this->getData('ip');
        return $value === null ? null : (string) $value;
    }

    public function setIp(?string $value): static
    {
        return $this->setData('ip', $value);
    }

    public function getRequestedDate(): ?string
    {
        $value = $this->getData('requested_date');
        return $value === null ? null : (string) $value;
    }

    public function setRequestedDate(?string $value): static
    {
        return $this->setData('requested_date', $value);
    }
}
