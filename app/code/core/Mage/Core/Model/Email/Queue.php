<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * @method Mage_Core_Model_Resource_Email_Queue _getResource()
 * @method Mage_Core_Model_Resource_Email_Queue_Collection getCollection()
 * @method $this setCreatedAt(string $value)
 * @method int getEntityId()
 * @method $this setEntityId(int $value)
 * @method string getEntityType()
 * @method $this setEntityType(string $value)
 * @method string getEventType()
 * @method $this setEventType(string $value)
 * @method int getIsForceCheck()
 * @method $this setIsForceCheck(int $value)
 * @method string getMessageBodyHash()
 * @method string getMessageBody()
 * @method $this setMessageBody(string $value)
 * @method $this setMessageBodyHash(string $value)
 * @method string getMessageParameters()
 * @method $this setMessageParameters(string $value)
 * @method $this setProcessedAt(string $value)
 */
class Mage_Core_Model_Email_Queue extends Mage_Core_Model_Abstract
{
    /**
     * Email types
     */
    public const EMAIL_TYPE_TO  = 0;
    public const EMAIL_TYPE_CC  = 1;
    public const EMAIL_TYPE_BCC = 2;

    /**
     * Maximum number of messages to be sent oer one cron run
     */
    public const MESSAGES_LIMIT_PER_CRON_RUN = 100;

    /**
     * Store message recipients list
     *
     * @var array
     */
    protected $_recipients = [];

    /**
     * Initialize object
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/email_queue');
    }

    /**
     * Save bind recipients to message
     */
    #[\Override]
    protected function _afterSave()
    {
        $this->_getResource()->saveRecipients($this->getId(), $this->getRecipients());
        return parent::_afterSave();
    }

    /**
     * Validate recipients before saving
     */
    #[\Override]
    protected function _beforeSave()
    {
        if (empty($this->_recipients) || !is_array($this->_recipients) || empty($this->_recipients[0])) { // additional check of recipients information (email address)
            $error = Mage::helper('core')->__('Message recipients data must be set.');
            Mage::throwException("{$error} - ID: " . $this->getId());
        }
        return parent::_beforeSave();
    }

    /**
     * Add message to queue
     *
     * @return $this
     */
    public function addMessageToQueue()
    {
        if ($this->getIsForceCheck() && $this->_getResource()->wasEmailQueued($this)) {
            return $this;
        }
        try {
            $this->save();
            $this->setId(null);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }

    /**
     * Add message recipients by email type
     *
     * @param array|string $emails
     * @param array|string|null $names
     * @param int $type
     * @return $this
     */
    public function addRecipients($emails, $names = null, $type = self::EMAIL_TYPE_TO)
    {
        $_supportedEmailTypes = [
            self::EMAIL_TYPE_TO,
            self::EMAIL_TYPE_CC,
            self::EMAIL_TYPE_BCC,
        ];
        $type = in_array($type, $_supportedEmailTypes) ? $type : self::EMAIL_TYPE_TO;
        $emails = array_values((array) $emails);
        $names = is_array($names) ? $names : (array) $names;
        $names = array_values($names);
        foreach ($emails as $key => $email) {
            $this->_recipients[] = [$email, $names[$key] ?? '', $type];
        }
        return $this;
    }

    /**
     * Clean recipients data from object
     *
     * @return $this
     */
    public function clearRecipients()
    {
        $this->_recipients = [];
        return $this;
    }

    /**
     * Set message recipients data
     *
     * @return $this
     */
    public function setRecipients(array $recipients)
    {
        $this->_recipients = $recipients;
        return $this;
    }

    /**
     * Get message recipients list
     *
     * @return array
     */
    public function getRecipients()
    {
        return $this->_recipients;
    }

    /**
     * Send all messages in a queue
     *
     * @return $this
     */
    public function send()
    {
        $collection = Mage::getModel('core/email_queue')->getCollection()
            ->addOnlyForSendingFilter()
            ->setPageSize(self::MESSAGES_LIMIT_PER_CRON_RUN)
            ->setCurPage(1)
            ->load();

        /** @var Mage_Core_Model_Email_Queue $message */
        foreach ($collection as $message) {
            if ($message->getId()) {
                $dsn = Mage::helper('core')->getMailerDsn();
                if (!$dsn) {
                    $message->setProcessedAt(Mage_Core_Model_Locale::now());
                    $message->save();
                    continue;
                }

                try {
                    $parameters = new \Maho\DataObject($message->getMessageParameters());
                    $mailer = new Mailer(Transport::fromDsn($dsn));
                    $email = new Email();
                    $email->subject($parameters->getSubject());
                    $email->from(new Address($parameters->getFromEmail(), $parameters->getFromName()));

                    foreach ($message->getRecipients() as $recipient) {
                        [$emailAddress, $name, $type] = $recipient;
                        $address = new Address($emailAddress, $name);

                        match ((int) $type) {
                            self::EMAIL_TYPE_BCC => $email->addBcc($address),
                            self::EMAIL_TYPE_CC => $email->addCc($address),
                            default => $email->addTo($address),
                        };
                    }

                    if ($parameters->getIsPlain()) {
                        $email->text($message->getMessageBody());
                    } else {
                        $email->html($message->getMessageBody());
                    }

                    if ($parameters->getReplyTo() !== null) {
                        $email->replyTo($parameters->getReplyTo());
                    }

                    if ($parameters->getReturnTo() !== null) {
                        $email->returnPath($parameters->getReturnTo());
                    }

                    $transport = new \Maho\DataObject();
                    Mage::dispatchEvent('email_queue_send_before', [
                        'mail'      => $email,
                        'message'   => $message,
                        'transport' => $transport,
                    ]);

                    $mailer->send($email);
                    $message->setProcessedAt(Mage_Core_Model_Locale::now());
                    $message->save();

                    foreach ($message->getRecipients() as $recipient) {
                        [$email, $name, $type] = $recipient;
                        Mage::dispatchEvent('email_queue_send_after', [
                            'to'         => $email,
                            'html'       => !$parameters->getIsPlain(),
                            'subject'    => $parameters->getSubject(),
                            'email_body' => $message->getMessageBody(),
                        ]);
                    }
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }

        return $this;
    }

    /**
     * Clean queue from sent messages
     *
     * @return $this
     */
    public function cleanQueue()
    {
        $this->_getResource()->removeSentMessages();
        return $this;
    }
}
