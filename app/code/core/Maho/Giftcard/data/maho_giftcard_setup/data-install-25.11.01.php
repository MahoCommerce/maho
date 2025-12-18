<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

// Load template content from file
$templateFile = Mage::getBaseDir('locale') . DS . 'en_US' . DS . 'template' . DS . 'email' . DS . 'giftcard_notification.html';
if (file_exists($templateFile)) {
    $templateContent = file_get_contents($templateFile);
    // Extract subject from template
    preg_match('/<!--@subject\s+(.*?)\s+@-->/', $templateContent, $matches);
    $subject = $matches[1] ?? 'Gift Card from {{var store_name}}';
} else {
    // Use default template if file doesn't exist
    $subject = 'Gift Card from {{var store_name}}';
    $templateContent = '<!--@subject Gift Card from {{var store_name}} @-->
<style type="text/css">
.giftcard-box { border: 2px solid #ddd; padding: 20px; margin: 20px 0; background: #f9f9f9; }
.giftcard-code { font-size: 24px; font-weight: bold; color: #333; padding: 10px; background: white; border: 1px solid #ccc; display: inline-block; margin: 10px 0; }
.giftcard-amount { font-size: 18px; color: #008000; font-weight: bold; }
</style>

<p>Dear {{var recipient_name}},</p>

<p>{{var sender_name}} has sent you a gift card!</p>

<div class="giftcard-box">
    <p><strong>Gift Card Code:</strong></p>
    <div class="giftcard-code">{{var code}}</div>

    <p><strong>Amount:</strong> <span class="giftcard-amount">{{var formatted_amount}}</span></p>

    {{depend message}}
    <p><strong>Message from {{var sender_name}}:</strong></p>
    <p style="font-style: italic;">{{var message}}</p>
    {{/depend}}

    <p><strong>Expires:</strong> {{var expiry_date}}</p>
</div>

<p>To use your gift card, enter the code at checkout or click the link below:</p>
<p><a href="{{store url="giftcard/index/redeem" _query_code=$code}}">Redeem Gift Card</a></p>

<p>Thank you for shopping with us!</p>
<p>{{var store_name}}</p>';
}

// Create email template in database
$emailTemplate = Mage::getModel('core/email_template');
$emailTemplate->setData([
    'template_code' => 'Maho Gift Card Notification',
    'template_text' => $templateContent,
    'template_type' => Mage_Core_Model_Email_Template::TYPE_HTML,
    'template_subject' => $subject,
    'orig_template_code' => 'maho_giftcard_email_template',
    'orig_template_variables' => json_encode([
        'store_name' => 'Store Name',
        'recipient_name' => 'Recipient Name',
        'sender_name' => 'Sender Name',
        'code' => 'Gift Card Code',
        'balance' => 'Gift Card Balance',
        'message' => 'Personal Message',
        'qr_url' => 'QR Code URL',
        'barcode_url' => 'Barcode URL',
        'expires_at' => 'Expiry Date',
        'store_url' => 'Store URL',
    ]),
]);
$emailTemplate->save();

// Update config to use this template
$installer->setConfigData('maho_giftcard/email/template', $emailTemplate->getId());
