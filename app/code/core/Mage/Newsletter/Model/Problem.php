<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

/**
 * Newsletter problem model
 *
 * @package    Mage_Newsletter
 *
 * @method Mage_Newsletter_Model_Resource_Problem _getResource()
 * @method Mage_Newsletter_Model_Resource_Problem getResource()
 */
class Mage_Newsletter_Model_Problem extends Mage_Core_Model_Abstract
{
    /**
     * Current Subscriber
     *
     * @var Mage_Newsletter_Model_Subscriber|null
     */
    protected $_subscriber = null;

    /**
     * Initialize Newsletter Problem Model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('newsletter/problem');
    }

    /**
     * Add Subscriber Data
     *
     * @return $this
     */
    public function addSubscriberData(Mage_Newsletter_Model_Subscriber $subscriber)
    {
        $this->setSubscriberId($subscriber->getId());
        return $this;
    }

    /**
     * Add Queue Data
     *
     * @return $this
     */
    public function addQueueData(Mage_Newsletter_Model_Queue $queue)
    {
        $this->setQueueId($queue->getId());
        return $this;
    }

    /**
     * Add Error Data
     *
     * @return $this
     */
    public function addErrorData(Exception $e)
    {
        $this->setProblemErrorCode($e->getCode());
        $this->setProblemErrorText($e->getMessage());
        return $this;
    }

    /**
     * Retrieve Subscriber
     *
     * @return Mage_Newsletter_Model_Subscriber|null
     */
    public function getSubscriber()
    {
        if (!$this->getSubscriberId()) {
            return null;
        }

        $this->_subscriber ??= Mage::getModel('newsletter/subscriber')
            ->load($this->getSubscriberId());

        return $this->_subscriber;
    }

    /**
     * Unsubscribe Subscriber
     *
     * @return $this
     */
    public function unsubscribe()
    {
        if ($this->getSubscriber()) {
            $this->getSubscriber()->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED)
                ->setIsStatusChanged(true)
                ->save();
        }
        return $this;
    }

    public function setCustomerFirstName(?string $value): static
    {
        return $this->setData('customer_first_name', $value);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerLastName(?string $value): static
    {
        return $this->setData('customer_last_name', $value);
    }

    public function setCustomerName(?string $value): static
    {
        return $this->setData('customer_name', $value);
    }

    public function getProblemErrorCode(): ?int
    {
        $value = $this->getData('problem_error_code');
        return $value === null ? null : (int) $value;
    }

    public function setProblemErrorCode(?int $value): static
    {
        return $this->setData('problem_error_code', $value);
    }

    public function getProblemErrorText(): ?string
    {
        $value = $this->getData('problem_error_text');
        return $value === null ? null : (string) $value;
    }

    public function setProblemErrorText(?string $value): static
    {
        return $this->setData('problem_error_text', $value);
    }

    public function getQueueId(): ?int
    {
        $value = $this->getData('queue_id');
        return $value === null ? null : (int) $value;
    }

    public function setQueueId(?int $value): static
    {
        return $this->setData('queue_id', $value);
    }

    public function getSubscriberId(): ?int
    {
        $value = $this->getData('subscriber_id');
        return $value === null ? null : (int) $value;
    }

    public function setSubscriberId(?int $value): static
    {
        return $this->setData('subscriber_id', $value);
    }

}
