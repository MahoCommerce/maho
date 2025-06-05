<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * Possible data fields:
 *
 * - subject
 * - to
 * - from
 * - body
 * - template (file name)
 * - module (for template)
 *
 * @method getFromEmail()
 * @method $this setFromEmail(string $string)
 * @method getFromName()
 * @method $this setFromName(string $string)
 * @method string getTemplate()
 * @method $this setTemplate(string $string)
 * @method string|array getToEmail()
 * @method $this setToEmail(string|array $string)
 * @method getToName()
 * @method $this setToName(string $string)
 * @method string getType()
 * @method $this setType(string $string)
 */
class Mage_Core_Model_Email extends Varien_Object
{
    protected $_tplVars = [];

    /**
     * @var Mage_Core_Block_Template
     */
    protected $_block;

    public function __construct()
    {
        // TODO: move to config
        $this->setFromName('Magento');
        $this->setFromEmail('magento@varien.com');
        $this->setType('text');
    }

    /**
     * @param string|array $var
     * @param string|null $value
     * @return $this
     */
    public function setTemplateVar($var, $value = null)
    {
        if (is_array($var)) {
            foreach ($var as $index => $value) {
                $this->_tplVars[$index] = $value;
            }
        } else {
            $this->_tplVars[$var] = $value;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getTemplateVars()
    {
        return $this->_tplVars;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        $body = $this->getData('body');
        if (empty($body) && $this->getTemplate()) {
            $this->_block = Mage::getModel('core/layout')
                ->createBlock('core/template', 'email')
                ->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND)
                ->setTemplate($this->getTemplate());
            foreach ($this->getTemplateVars() as $var => $value) {
                $this->_block->assign($var, $value);
            }
            $this->_block->assign('_type', strtolower($this->getType()))
                ->assign('_section', 'body');
            $body = $this->_block->toHtml();
        }
        return $body;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        $subject = $this->getData('subject');
        if (empty($subject) && $this->_block) {
            $this->_block->assign('_section', 'subject');
            $subject = $this->_block->toHtml();
        }
        return $subject;
    }

    /**
     * @return $this
     */
    public function send()
    {
        if (Mage::getStoreConfigFlag('system/smtp/disable')) {
            return $this;
        }

        $dsn = Mage::helper('core')->getMailerDsn();
        if (!$dsn) {
            // This means email sending is disabled
            return $this;
        }

        try {
            $email = new Email();
            $email->subject($this->getSubject());
            $email->from(new Address($this->getFromEmail(), $this->getFromName()));

            $toEmails = is_array($this->getToEmail()) ? $this->getToEmail() : [$this->getToEmail()];
            $toNames = is_array($this->getToName()) ? $this->getToName() : [$this->getToName()];

            foreach ($toEmails as $key => $toEmail) {
                $toName = $toNames[$key] ?? '';
                $email->addTo(new Address($toEmail, $toName));
            }

            if (strtolower($this->getType()) == 'html') {
                $email->html($this->getBody());
            } else {
                $email->text($this->getBody());
            }

            $transport = new Varien_Object();
            Mage::dispatchEvent('email_send_before', [
                'mail'      => $email,
                'template'  => $this->getTemplate(),
                'transport' => $transport,
                'variables' => $this->getTemplateVars(),
            ]);

            $mailer = new Mailer(Transport::fromDsn($dsn));
            $mailer->send($email);

            Mage::dispatchEvent('email_send_after', [
                'to'         => $this->getToEmail(),
                'subject'    => $this->getSubject(),
                'email_body' => $this->getBody(),
            ]);

            return $this;
        } catch (Exception $e) {
            Mage::logException($e);
            return $this;
        }
    }
}
