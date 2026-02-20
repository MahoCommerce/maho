<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Email_Transport
{
    private const SCHEME_TO_PACKAGE_MAP = [
        'azure' => 'symfony/azure-mailer',
        'brevo' => 'symfony/brevo-mailer',
        'gmail' => 'symfony/google-mailer',
        'infobip' => 'symfony/infobip-mailer',
        'mailersend' => 'symfony/mailersend-mailer',
        'mailgun' => 'symfony/mailgun-mailer',
        'mailjet' => 'symfony/mailjet-mailer',
        'mailomat' => 'symfony/mailomat-mailer',
        'mailpace' => 'symfony/mail-pace-mailer',
        'mandrill' => 'symfony/mailchimp-mailer',
        'postal' => 'symfony/postal-mailer',
        'postmark' => 'symfony/postmark-mailer',
        'mailtrap' => 'symfony/mailtrap-mailer',
        'resend' => 'symfony/resend-mailer',
        'scaleway' => 'symfony/scaleway-mailer',
        'sendgrid' => 'symfony/sendgrid-mailer',
        'ses' => 'symfony/amazon-mailer',
        'sweego' => 'symfony/sweego-mailer',
    ];

    public function toOptionArray(): array
    {
        $options = [
            ['value' => '0', 'label' => Mage::helper('adminhtml')->__('Disable All Email Communications')],
            ['value' => 'sendmail', 'label' => 'Sendmail'],
            ['value' => 'smtp', 'label' => 'SMTP'],
            ['value' => 'ses+smtp', 'label' => 'Amazon SES - SMTP'],
            ['value' => 'ses+https', 'label' => 'Amazon SES - HTTPS'],
            ['value' => 'ses+api', 'label' => 'Amazon SES - API'],
            ['value' => 'azure+api', 'label' => 'Azure'],
            ['value' => 'brevo+smtp', 'label' => 'Brevo - SMTP'],
            ['value' => 'brevo+api', 'label' => 'Brevo - API'],
            ['value' => 'gmail+smtp', 'label' => 'Gmail'],
            ['value' => 'infobip+smtp', 'label' => 'Infobip - SMTP'],
            ['value' => 'infobip+api', 'label' => 'Infobip - API'],
            ['value' => 'mandrill+smtp', 'label' => 'Mandrill - SMTP'],
            ['value' => 'mandrill+https', 'label' => 'Mandrill - HTTPS'],
            ['value' => 'mandrill+api', 'label' => 'Mandrill - API'],
            ['value' => 'mailersend+smtp', 'label' => 'Mailersend - SMTP'],
            ['value' => 'mailersend+api', 'label' => 'Mailersend - API'],
            ['value' => 'mailgun+smtp', 'label' => 'Mailgun - SMTP'],
            ['value' => 'mailgun+https', 'label' => 'Mailgun - HTTPS'],
            ['value' => 'mailgun+api', 'label' => 'Mailgun - API'],
            ['value' => 'mailjet+smtp', 'label' => 'Mailjet - SMTP'],
            ['value' => 'mailjet+api', 'label' => 'Mailjet - API'],
            ['value' => 'mailomat+smtp', 'label' => 'Mailomat - SMTP'],
            ['value' => 'mailomat+api', 'label' => 'Mailomat - API'],
            ['value' => 'mailpace+smtp', 'label' => 'Mailpace - SMTP'],
            ['value' => 'mailpace+api', 'label' => 'Mailpace - API'],
            ['value' => 'mailtrap+smtp', 'label' => 'Mailtrap - SMTP'],
            ['value' => 'mailtrap+api', 'label' => 'Mailtrap - API'],
            ['value' => 'postal+api', 'label' => 'Postal'],
            ['value' => 'postmark+smtp', 'label' => 'Postmark - SMTP'],
            ['value' => 'postmark+api', 'label' => 'Postmark - API'],
            ['value' => 'resend+smtp', 'label' => 'Resend - SMTP'],
            ['value' => 'resend+api', 'label' => 'Resend - API'],
            ['value' => 'scaleway+smtp', 'label' => 'Scaleway - SMTP'],
            ['value' => 'scaleway+api', 'label' => 'Scaleway - API'],
            ['value' => 'sendgrid+smtp', 'label' => 'Sendgrid - SMTP'],
            ['value' => 'sendgrid+api', 'label' => 'Sendgrid - API'],
            ['value' => 'sweego+smtp', 'label' => 'Sweego - SMTP'],
            ['value' => 'sweego+api', 'label' => 'Sweego - API'],
        ];

        foreach ($options as $k => $option) {
            if (!str_contains($option['value'], '+')) {
                continue;
            }

            $scheme = explode('+', $option['value']);
            $scheme = $scheme[0];

            if (isset(self::SCHEME_TO_PACKAGE_MAP[$scheme])) {
                $package = self::SCHEME_TO_PACKAGE_MAP[$scheme];
                if (!\Composer\InstalledVersions::isInstalled($package)) {
                    $options[$k]['label'] .= " ⚠️ Install $package";
                }
            }
        }

        return $options;
    }
}
