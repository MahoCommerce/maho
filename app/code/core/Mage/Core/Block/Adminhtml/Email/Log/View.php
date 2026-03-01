<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Core_Block_Adminhtml_Email_Log_View extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->_headerText = Mage::helper('core')->__('Email Log Entry');

        $this->_addButton('back', [
            'label'   => Mage::helper('core')->__('Back'),
            'onclick' => "window.location.href = '{$this->escapeUrl($this->getUrl('*/*/'))}';",
            'class'   => 'back',
        ]);
    }

    public function getLog(): ?Mage_Core_Model_Email_Log
    {
        return Mage::registry('current_email_log');
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $log = $this->getLog();
        if (!$log || !$log->getId()) {
            return '';
        }

        $helper = Mage::helper('core');
        $html = '<div class="content-header"><h3>' . $this->escapeHtml($this->_headerText) . '</h3>'
            . '<p class="content-buttons form-buttons">' . $this->getButtonsHtml() . '</p></div>';

        $html .= '<div class="entry-edit">';
        $html .= '<div class="entry-edit-head"><h4>' . $helper->__('Email Details') . '</h4></div>';
        $html .= '<fieldset class="fieldset"><table class="form-list"><tbody>';

        $rows = [
            ['label' => $helper->__('ID'),      'value' => $log->getId()],
            ['label' => $helper->__('Status'),   'value' => $log->getStatus() === 'sent'
                ? '<span style="color:green;font-weight:bold">' . $helper->__('Sent') . '</span>'
                : '<span style="color:red;font-weight:bold">' . $helper->__('Failed') . '</span>'],
            ['label' => $helper->__('Date'),     'value' => $this->escapeHtml($log->getCreatedAt())],
            ['label' => $helper->__('Subject'),  'value' => $this->escapeHtml($log->getSubject())],
            ['label' => $helper->__('From'),     'value' => $this->escapeHtml($log->getEmailFrom())],
            ['label' => $helper->__('To'),       'value' => $this->escapeHtml($log->getEmailTo())],
            ['label' => $helper->__('Cc'),       'value' => $this->escapeHtml((string) $log->getEmailCc())],
            ['label' => $helper->__('Bcc'),      'value' => $this->escapeHtml((string) $log->getEmailBcc())],
            ['label' => $helper->__('Type'),     'value' => strtoupper($log->getContentType())],
        ];

        if ($log->getTemplate()) {
            $rows[] = ['label' => $helper->__('Template'), 'value' => $this->escapeHtml($log->getTemplate())];
        }

        if ($log->getErrorMessage()) {
            $rows[] = ['label' => $helper->__('Error'), 'value' => '<span style="color:red">' . $this->escapeHtml($log->getErrorMessage()) . '</span>'];
        }

        foreach ($rows as $row) {
            $html .= '<tr><td class="label"><strong>' . $row['label'] . ':</strong></td>'
                . '<td class="value">' . $row['value'] . '</td></tr>';
        }

        $html .= '</tbody></table></fieldset></div>';

        // Email body preview in sandboxed iframe
        $html .= '<div class="entry-edit" style="margin-top:15px">';
        $html .= '<div class="entry-edit-head"><h4>' . $helper->__('Email Body') . '</h4></div>';
        $html .= '<fieldset class="fieldset">';

        if ($log->getContentType() === 'html') {
            $containerId = 'email-body-' . $log->getId();
            $html .= '<div id="' . $containerId . '"></div>'
                . '<script>document.addEventListener("DOMContentLoaded",function(){'
                . 'var c=document.getElementById(' . Mage::helper('core')->jsonEncode($containerId) . ');'
                . 'var s=c.attachShadow({mode:"closed"});'
                . 's.innerHTML=' . Mage::helper('core')->jsonEncode($log->getEmailBody()) . ';'
                . '});</script>';
        } else {
            $html .= '<pre style="white-space:pre-wrap;background:#f5f5f5;padding:15px;border:1px solid #ccc">'
                . $this->escapeHtml($log->getEmailBody())
                . '</pre>';
        }

        $html .= '</fieldset></div>';

        return $html;
    }
}
