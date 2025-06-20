<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this setQueue(Mage_Core_Model_Abstract $value)
 * @method Mage_Core_Model_Email_Queue getQueue()
 */
class Mage_Core_Model_Email_Template_Mailer extends Varien_Object
{
    /**
     * List of email infos
     * @see Mage_Core_Model_Email_Info
     *
     * @var array
     */
    protected $_emailInfos = [];

    /**
     * Add new email info to corresponding list
     *
     * @return $this
     */
    public function addEmailInfo(Mage_Core_Model_Email_Info $emailInfo)
    {
        $this->_emailInfos[] = $emailInfo;
        return $this;
    }

    /**
     * Send all emails from email list
     * @see self::$_emailInfos
     *
     * @return $this
     */
    public function send()
    {
        $overallSuccess = true;
        $emailTransport = Mage::getStoreConfig('system/smtp/enabled', $this->getStoreId());
        $isAmazonSes = in_array($emailTransport, ['ses+smtp', 'ses+https', 'ses+api']);
        $sendIndividuallyForSesBcc = $isAmazonSes && Mage::getStoreConfigFlag('system/smtp/ses_bcc_individual', $this->getStoreId());
        
        // Send all emails from corresponding list
        while (!empty($this->_emailInfos)) {
            $emailInfo = array_pop($this->_emailInfos);
            
            // Check if we need to send individual emails for SES with BCC
            $hasBcc = !empty($emailInfo->getBccEmails());
            $shouldSendIndividually = $sendIndividuallyForSesBcc && $hasBcc;
            
            if ($shouldSendIndividually) {
                // For SES with BCC, send individual emails to maintain privacy
                $allRecipients = array_merge($emailInfo->getToEmails(), $emailInfo->getBccEmails());
                $toNames = $emailInfo->getToNames();
                
                foreach ($allRecipients as $index => $recipientEmail) {
                    try {
                        // Create a fresh template instance for each email
                        /** @var Mage_Core_Model_Email_Template $emailTemplate */
                        $emailTemplate = Mage::getModel('core/email_template');
                        
                        // Determine recipient name (use the corresponding name if available, otherwise null)
                        $recipientName = null;
                        if ($index < count($emailInfo->getToEmails()) && isset($toNames[$index])) {
                            $recipientName = $toNames[$index];
                        }
                        
                        $emailTemplate
                            ->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $this->getStoreId()])
                            ->setQueue($this->getQueue())
                            ->sendTransactional(
                                $this->getTemplateId(),
                                $this->getSender(),
                                $recipientEmail,
                                $recipientName,
                                $this->getTemplateParams(),
                                $this->getStoreId()
                            );
                        
                        if (!$emailTemplate->getSentSuccess()) {
                            $overallSuccess = false;
                            Mage::log("Failed to send individual email to {$recipientEmail} (SES BCC mode)", Zend_Log::ERR, 'email.log');
                        }
                        
                    } catch (Exception $e) {
                        $overallSuccess = false;
                        Mage::log("Exception sending individual email to {$recipientEmail}: " . $e->getMessage(), Zend_Log::ERR, 'email.log');
                        Mage::logException($e);
                    }
                }
            } else {
                // Normal send for non-SES or when no BCC
                try {
                    // Create a fresh template instance for each email to avoid BCC accumulation
                    /** @var Mage_Core_Model_Email_Template $emailTemplate */
                    $emailTemplate = Mage::getModel('core/email_template');
                    // Handle "Bcc" recipients of the current email
                    $emailTemplate->addBcc($emailInfo->getBccEmails());
                    // Set required design parameters and delegate email sending to Mage_Core_Model_Email_Template
                    $emailTemplate
                        ->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $this->getStoreId()])
                        ->setQueue($this->getQueue())
                        ->sendTransactional(
                            $this->getTemplateId(),
                            $this->getSender(),
                            $emailInfo->getToEmails(),
                            $emailInfo->getToNames(),
                            $this->getTemplateParams(),
                            $this->getStoreId()
                        );
                    
                    if (!$emailTemplate->getSentSuccess()) {
                        $overallSuccess = false;
                        $toEmails = implode(', ', $emailInfo->getToEmails());
                        Mage::log("Failed to send email to {$toEmails}", Zend_Log::ERR, 'email.log');
                    }
                    
                } catch (Exception $e) {
                    $overallSuccess = false;
                    $toEmails = implode(', ', $emailInfo->getToEmails());
                    Mage::log("Exception sending email to {$toEmails}: " . $e->getMessage(), Zend_Log::ERR, 'email.log');
                    Mage::logException($e);
                }
            }
        }
        
        // Store overall success status if needed by calling code
        $this->setData('sent_success', $overallSuccess);
        
        return $this;
    }

    /**
     * Set email sender
     *
     * @param string|array $sender
     * @return $this
     */
    public function setSender($sender)
    {
        return $this->setData('sender', $sender);
    }

    /**
     * Get email sender
     *
     * @return string|array|null
     */
    public function getSender()
    {
        return $this->_getData('sender');
    }

    /**
     * Set store id
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        return $this->setData('store_id', $storeId);
    }

    /**
     * Get store id
     *
     * @return int|null
     */
    public function getStoreId()
    {
        return $this->_getData('store_id');
    }

    /**
     * Set template id
     *
     * @param int $templateId
     * @return $this
     */
    public function setTemplateId($templateId)
    {
        return $this->setData('template_id', $templateId);
    }

    /**
     * Get template id
     *
     * @return int|null
     */
    public function getTemplateId()
    {
        return $this->_getData('template_id');
    }

    /**
     * Set template parameters
     *
     * @return $this
     */
    public function setTemplateParams(array $templateParams)
    {
        return $this->setData('template_params', $templateParams);
    }

    /**
     * Get template parameters
     *
     * @return array|null
     */
    public function getTemplateParams()
    {
        return $this->_getData('template_params');
    }
}
