<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * Email information model
 * Email message may contain addresses in any of these three fields:
 *  -To:  Primary recipients
 *  -Cc:  Carbon copy to secondary recipients and other interested parties
 *  -Bcc: Blind carbon copy to tertiary recipients who receive the message
 *        without anyone else (including the To, Cc, and Bcc recipients) seeing who the tertiary recipients are
 */
class Mage_Core_Model_Email_Info extends \Maho\DataObject
{
    /**
     * Name list of "Bcc" recipients
     *
     * @var array
     */
    protected $_bccNames = [];

    /**
     * Email list of "Bcc" recipients
     *
     * @var array
     */
    protected $_bccEmails = [];

    /**
     * Name list of "To" recipients
     *
     * @var array
     */
    protected $_toNames = [];

    /**
     * Email list of "To" recipients
     *
     * @var array
     */
    protected $_toEmails = [];

    /**
     * List of attachments to add to the email
     *
     * @var Mage_Core_Model_Email_Attachment[]
     */
    protected $_attachments = [];

    /**
     * Add new "Bcc" recipient to current email
     *
     * @param string $email
     * @param string|null $name
     * @return $this
     */
    public function addBcc(#[\SensitiveParameter] $email, $name = null)
    {
        $this->_bccNames[] = $name;
        $this->_bccEmails[] = $email;
        return $this;
    }

    /**
     * Add new "To" recipient to current email
     *
     * @param array|string $email
     * @param array|string|null $name
     * @return $this
     */
    public function addTo(#[\SensitiveParameter] $email, $name = null)
    {
        $this->_toNames[] = $name;
        $this->_toEmails[] = $email;
        return $this;
    }

    /**
     * Add an attachment to the current email
     *
     * @return $this
     */
    public function addAttachment(Mage_Core_Model_Email_Attachment $attachment)
    {
        $this->_attachments[] = $attachment;
        return $this;
    }

    /**
     * Add a lazily-generated PDF document attachment
     *
     * @return $this
     */
    public function addPdfAttachment(string $sourceModel, string $entityModel, int $entityId, string $filename)
    {
        return $this->addAttachment(
            new Mage_Core_Model_Email_Attachment($sourceModel, $entityModel, $entityId, $filename),
        );
    }

    /**
     * Get the list of attachments
     *
     * @return Mage_Core_Model_Email_Attachment[]
     */
    public function getAttachments()
    {
        return $this->_attachments;
    }

    /**
     * Get the name list of "Bcc" recipients
     *
     * @return array
     */
    public function getBccNames()
    {
        return $this->_bccNames;
    }

    /**
     * Get the email list of "Bcc" recipients
     *
     * @return array
     */
    public function getBccEmails()
    {
        return $this->_bccEmails;
    }

    /**
     * Get the name list of "To" recipients
     *
     * @return array
     */
    public function getToNames()
    {
        return $this->_toNames;
    }

    /**
     * Get the email list of "To" recipients
     *
     * @return array
     */
    public function getToEmails()
    {
        return $this->_toEmails;
    }
}
