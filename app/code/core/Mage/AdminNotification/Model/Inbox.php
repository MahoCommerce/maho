<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_AdminNotification
 */

/**
 * AdminNotification Inbox model
 *
 * @package    Mage_AdminNotification
 *
 * @method Mage_AdminNotification_Model_Resource_Inbox _getResource()
 * @method Mage_AdminNotification_Model_Resource_Inbox getResource()
 * @method Mage_AdminNotification_Model_Resource_Inbox_Collection getCollection()
 */
class Mage_AdminNotification_Model_Inbox extends Mage_Core_Model_Abstract
{
    public const SEVERITY_CRITICAL = 1;
    public const SEVERITY_MAJOR    = 2;
    public const SEVERITY_MINOR    = 3;
    public const SEVERITY_NOTICE   = 4;

    #[\Override]
    protected function _construct()
    {
        $this->_init('adminnotification/inbox');
    }

    /**
     * Retrieve Severity collection array
     *
     * @param int|null $severity
     * @return array|string|null
     */
    public function getSeverities($severity = null)
    {
        $severities = [
            self::SEVERITY_CRITICAL => Mage::helper('adminnotification')->__('critical'),
            self::SEVERITY_MAJOR    => Mage::helper('adminnotification')->__('major'),
            self::SEVERITY_MINOR    => Mage::helper('adminnotification')->__('minor'),
            self::SEVERITY_NOTICE   => Mage::helper('adminnotification')->__('notice'),
        ];

        if (!is_null($severity)) {
            return $severities[$severity] ?? null;
        }

        return $severities;
    }

    /**
     * Retrieve Latest Notice
     *
     * @return $this
     */
    public function loadLatestNotice()
    {
        $this->setData([]);
        $this->getResource()->loadLatestNotice($this);
        return $this;
    }

    /**
     * Retrieve notice statuses
     *
     * @return array
     */
    public function getNoticeStatus()
    {
        return $this->getResource()->getNoticeStatus($this);
    }

    /**
     * Parse and save new data
     *
     * @return $this
     */
    public function parse(array $data)
    {
        return $this->getResource()->parse($this, $data);
    }

    /**
     * Add new message
     *
     * @param int $severity
     * @param string $title
     * @param string|array $description
     * @param string $url
     * @param bool $isInternal
     * @return $this
     */
    public function add($severity, $title, $description, $url = '', $isInternal = true)
    {
        if (!$this->getSeverities($severity)) {
            Mage::throwException(Mage::helper('adminnotification')->__('Wrong message type'));
        }
        if (is_array($description)) {
            $description = '<ul><li>' . implode('</li><li>', $description) . '</li></ul>';
        }
        $date = date(Maho\Db\Adapter\Pdo\Mysql::TIMESTAMP_FORMAT);
        $this->parse([[
            'severity'    => $severity,
            'date_added'  => $date,
            'title'       => $title,
            'description' => $description,
            'url'         => $url,
            'internal'    => $isInternal,
        ]]);
        return $this;
    }

    /**
     * Add critical severity message
     *
     * @param string $title
     * @param string|array $description
     * @param string $url
     * @param bool $isInternal
     * @return $this
     */
    public function addCritical($title, $description, $url = '', $isInternal = true)
    {
        $this->add(self::SEVERITY_CRITICAL, $title, $description, $url, $isInternal);
        return $this;
    }

    /**
     * Add major severity message
     *
     * @param string $title
     * @param string|array $description
     * @param string $url
     * @param bool $isInternal
     * @return $this
     */
    public function addMajor($title, $description, $url = '', $isInternal = true)
    {
        $this->add(self::SEVERITY_MAJOR, $title, $description, $url, $isInternal);
        return $this;
    }

    /**
     * Add minor severity message
     *
     * @param string $title
     * @param string|array $description
     * @param string $url
     * @param bool $isInternal
     * @return $this
     */
    public function addMinor($title, $description, $url = '', $isInternal = true)
    {
        $this->add(self::SEVERITY_MINOR, $title, $description, $url, $isInternal);
        return $this;
    }

    /**
     * Add notice
     *
     * @param string $title
     * @param string|array $description
     * @param string $url
     * @param bool $isInternal
     * @return $this
     */
    public function addNotice($title, $description, $url = '', $isInternal = true)
    {
        $this->add(self::SEVERITY_NOTICE, $title, $description, $url, $isInternal);
        return $this;
    }

    public function getDateAdded(): ?string
    {
        $value = $this->getData('date_added');
        return $value === null ? null : (string) $value;
    }

    public function setDateAdded(?string $value): static
    {
        return $this->setData('date_added', $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
    }

    public function getIsRead(): ?bool
    {
        $value = $this->getData('is_read');
        return $value === null ? null : (bool) $value;
    }

    public function setIsRead(?bool $value = true): static
    {
        return $this->setData('is_read', $value);
    }

    public function getIsRemove(): ?bool
    {
        $value = $this->getData('is_remove');
        return $value === null ? null : (bool) $value;
    }

    public function setIsRemove(?bool $value = true): static
    {
        return $this->setData('is_remove', $value);
    }

    public function getSeverity(): ?int
    {
        $value = $this->getData('severity');
        return $value === null ? null : (int) $value;
    }

    public function setSeverity(?int $value): static
    {
        return $this->setData('severity', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function getUrl(): ?string
    {
        $value = $this->getData('url');
        return $value === null ? null : (string) $value;
    }

    public function setUrl(?string $value): static
    {
        return $this->setData('url', $value);
    }

}
