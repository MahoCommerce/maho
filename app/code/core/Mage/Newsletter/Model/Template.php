<?php

/**
 * Maho
 *
 * @package    Mage_Newsletter
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Template model
 *
 * @package    Mage_Newsletter
 *
 * @method Mage_Newsletter_Model_Resource_Template _getResource()
 * @method Mage_Newsletter_Model_Resource_Template getResource()
 * @method string getTemplateCode()
 * @method $this setTemplateCode(string $value)
 * @method $this setTemplateText(string $value)
 * @method $this setTemplateTextPreprocessed(string $value)
 * @method string getTemplateStyles()
 * @method $this setTemplateStyles(string $value)
 * @method int getTemplateType()
 * @method $this setTemplateType(int $value)
 * @method string getTemplateSubject()
 * @method $this setTemplateSubject(string $value)
 * @method string getTemplateSenderName()
 * @method $this setTemplateSenderName(string $value)
 * @method string getTemplateSenderEmail()
 * @method $this setTemplateSenderEmail(string $value)
 * @method bool hasTemplateActual()
 * @method int getTemplateActual()
 * @method $this setTemplateActual(int $value)
 * @method bool hasAddedAt()
 * @method string getAddedAt()
 * @method $this setAddedAt(string $value)
 * @method string getModifiedAt()
 * @method $this setModifiedAt(string $value)
 * @method bool getIsSystem()
 * @method $this setInlineCssFile(bool|string $value)
 */
class Mage_Newsletter_Model_Template extends Mage_Core_Model_Email_Template_Abstract
{
    /**
     * Template Text Preprocessed flag
     *
     * @var bool
     */
    protected $_preprocessFlag = false;

    /**
     * Mail object
     *
     * @var Symfony\Component\Mime\Email|null
     */
    protected $_mail;

    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('newsletter/template');
    }

    /**
     * Validate Newsletter template
     *
     * @throws Mage_Core_Exception
     */
    public function validate()
    {
        $errorMessages = [];
        $helper = Mage::helper('core');

        // Validate template code
        if (empty(trim($this->getTemplateCode() ?? ''))) {
            $errorMessages[] = Mage::helper('newsletter')->__('Template code is required');
        }

        // Validate template type
        if (!is_numeric($this->getTemplateType())) {
            $errorMessages[] = Mage::helper('newsletter')->__('Template type must be numeric');
        }

        // Validate sender email
        if (!$helper->isValidEmail($this->getTemplateSenderEmail())) {
            $errorMessages[] = Mage::helper('newsletter')->__('Invalid sender email address');
        }

        // Validate sender name
        if (empty(trim($this->getTemplateSenderName() ?? ''))) {
            $errorMessages[] = Mage::helper('newsletter')->__('Sender name is required');
        }

        if ($errorMessages) {
            Mage::throwException(implode("\n", $errorMessages));
        }
    }

    #[\Override]
    protected function _beforeSave()
    {
        $this->validate();
        return parent::_beforeSave();
    }

    /**
     * Load template by code
     *
     * @param string $templateCode
     * @return $this
     */
    public function loadByCode($templateCode)
    {
        $this->_getResource()->loadByCode($this, $templateCode);
        return $this;
    }

    /**
     * @return bool
     */
    public function isValidForSend()
    {
        return !Mage::getStoreConfigFlag('system/smtp/disable')
            && $this->getTemplateSenderName()
            && $this->getTemplateSenderEmail()
            && $this->getTemplateSubject();
    }

    /**
     * Getter for template type
     *
     * @return int|string
     */
    #[\Override]
    public function getType()
    {
        return $this->getTemplateType();
    }

    /**
     * Check is Preprocessed
     *
     * @return bool
     */
    public function isPreprocessed()
    {
        return (string) ($this->getTemplateTextPreprocessed() ?? '') !== '';
    }

    /**
     * Check Template Text Preprocessed
     *
     * @return string
     */
    public function getTemplateTextPreprocessed()
    {
        if ($this->_preprocessFlag) {
            $this->setTemplateTextPreprocessed($this->getProcessedTemplate());
        }

        return (string) $this->getData('template_text_preprocessed');
    }

    /**
     * Retrieve processed template
     *
     * @param bool $usePreprocess
     * @return string
     */
    public function getProcessedTemplate(array $variables = [], $usePreprocess = false)
    {
        /** @var Mage_Newsletter_Model_Template_Filter $processor */
        $processor = Mage::helper('newsletter')->getTemplateProcessor();

        if (!$this->_preprocessFlag) {
            $variables['this'] = $this;
        }

        if (Mage::app()->isSingleStoreMode()) {
            $processor->setStoreId(Mage::app()->getStore());
        } else {
            $processor->setStoreId(Mage::app()->getRequest()->getParam('store_id'));
        }

        // Populate the variables array with store, store info, logo, etc. variables
        $variables = $this->_addEmailVariables($variables, $processor->getStoreId());

        $processor
            ->setTemplateProcessor([$this, 'getTemplateByConfigPath'])
            ->setIncludeProcessor([$this, 'getInclude'])
            ->setVariables($variables);

        // Filter the template text so that all HTML content will be present
        $result = $processor->filter($this->getTemplateText());
        // If the {{inlinecss file=""}} directive was included in the template, grab filename to use for inlining
        $this->setInlineCssFile($processor->getInlineCssFile());

        // Now that all HTML has been assembled, run email through CSS inlining process
        if ($usePreprocess && $this->isPreprocessed()) {
            $processedResult = $this->getPreparedTemplateText(true, $result);
        } else {
            $processedResult = $this->getPreparedTemplateText(false, $result);
        }

        return $processedResult;
    }

    /**
     * Makes additional text preparations for HTML templates
     *
     * @param bool $usePreprocess Use Preprocessed text or original text
     * @param string|null $html
     * @return string
     */
    public function getPreparedTemplateText($usePreprocess = false, $html = null)
    {
        if ($usePreprocess) {
            $text = $this->getTemplateTextPreprocessed();
        } elseif ($html) {
            $text = $html;
        } else {
            $text = $this->getTemplateText();
        }

        if ($this->_preprocessFlag || $this->isPlain()) {
            return $text;
        }

        return $this->_applyInlineCss($text);
    }

    /**
     * Retrieve included template
     *
     * @param string $templateCode
     * @return string
     */
    public function getInclude($templateCode, array $variables)
    {
        return Mage::getModel('newsletter/template')
            ->loadByCode($templateCode)
            ->getProcessedTemplate($variables);
    }

    /**
     * Retrieve processed template subject
     *
     * @return string
     */
    public function getProcessedTemplateSubject(array $variables)
    {
        $processor = new \Maho\Filter\Template();

        if (!$this->_preprocessFlag) {
            $variables['this'] = $this;
        }

        $processor->setVariables($variables);
        return $processor->filter($this->getTemplateSubject());
    }

    /**
     * Retrieve template text wrapper
     *
     * @return string
     */
    public function getTemplateText()
    {
        if (!$this->getData('template_text') && !$this->getId()) {
            $this->setData(
                'template_text',
                '<p>' . Mage::helper('newsletter')->__('Follow this link to unsubscribe:') . ' <a href="{{var subscriber.getUnsubscriptionLink()}}">' . Mage::helper('newsletter')->__('Unsubscribe') . '</a></p>',
            );
        }

        return $this->getData('template_text');
    }
}
