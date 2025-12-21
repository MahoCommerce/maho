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

class Maho_Giftcard_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Generate a unique gift card code
     */
    public function generateCode(): string
    {
        $length = (int) Mage::getStoreConfig('giftcard/general/code_length') ?: 16;
        $prefix = (string) Mage::getStoreConfig('giftcard/general/code_prefix');
        $format = Mage::getStoreConfig('giftcard/general/code_format');

        // Generate random alphanumeric code
        $characters = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluding I, O to avoid confusion
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Apply format if specified (e.g., XXXX-XXXX-XXXX-XXXX)
        if ($format && str_contains($format, 'X')) {
            $formattedCode = '';
            $codeIndex = 0;

            for ($i = 0; $i < strlen($format); $i++) {
                if ($format[$i] === 'X') {
                    $formattedCode .= $code[$codeIndex] ?? '';
                    $codeIndex++;
                } else {
                    $formattedCode .= $format[$i];
                }
            }

            $code = $formattedCode;
        }

        // Add prefix
        if ($prefix) {
            $code = $prefix . '-' . $code;
        }

        // Check if code already exists, regenerate if it does
        $giftcard = Mage::getModel('giftcard/giftcard')
            ->loadByCode($code);

        if ($giftcard->getId()) {
            return $this->generateCode(); // Recursively generate new code
        }

        return $code;
    }

    /**
     * Format gift card code for display
     */
    public function formatCode(string $code): string
    {
        // Already formatted during generation
        return strtoupper($code);
    }

    /**
     * Check if gift card module is enabled
     */
    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag('giftcard/general/enabled');
    }

    /**
     * Get gift card lifetime in days
     *
     * @return int 0 = no expiration
     */
    public function getLifetime(): int
    {
        return (int) Mage::getStoreConfig('giftcard/general/lifetime');
    }

    /**
     * Calculate expiration date from now
     */
    public function calculateExpirationDate(): ?string
    {
        $lifetime = $this->getLifetime();

        if ($lifetime === 0) {
            return null; // No expiration
        }

        $expirationDate = new DateTime();
        $expirationDate->modify("+{$lifetime} days");

        return $expirationDate->format('Y-m-d H:i:s');
    }

    /**
     * Format currency amount
     */
    public function formatAmount(float $amount, ?string $currencyCode = null): string
    {
        if (!$currencyCode) {
            $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        }

        // Use Maho's currency model for formatting
        return Mage::getModel('directory/currency')->load($currencyCode)->format($amount, [], false);
    }

    /**
     * Check if QR code generation is enabled
     */
    public function isQrCodeEnabled(): bool
    {
        return Mage::getStoreConfigFlag('giftcard/general/enable_qrcode');
    }

    /**
     * Check if barcode generation is enabled
     */
    public function isBarcodeEnabled(): bool
    {
        return Mage::getStoreConfigFlag('giftcard/general/enable_barcode');
    }

    /**
     * Generate QR code data URL for gift card code
     */
    public function getQrCodeDataUrl(string $code, int $size = 200): string
    {
        if (!$this->isQrCodeEnabled()) {
            return '';
        }

        try {
            // Use BaconQrCode for local QR generation
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            );

            $writer = new \BaconQrCode\Writer($renderer);

            // Generate SVG QR code
            $svg = $writer->writeString($code);

            // Convert to data URL
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Exception $e) {
            Mage::logException($e);
            return '';
        }
    }

    /**
     * Generate barcode data URL for gift card code (Code128)
     */
    public function getBarcodeDataUrl(string $code): string
    {
        if (!$this->isBarcodeEnabled()) {
            return '';
        }

        try {
            // Use Picqer for local barcode generation
            $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();

            // Generate Code128 barcode as SVG
            $svg = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 60);

            // Convert to data URL
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Exception $e) {
            Mage::logException($e);
            return '';
        }
    }

    /**
     * Get QR code SVG for gift card code
     */
    public function getQrCodeSvg(string $code): string
    {
        if (!$this->isQrCodeEnabled()) {
            return '';
        }

        // Return the data URL directly as SVG
        return $this->getQrCodeDataUrl($code, 200);
    }

    /**
     * Get QR code URL (alias for getQrCodeDataUrl for backward compatibility)
     */
    public function getQrCodeUrl(string $code, int $size = 200): string
    {
        return $this->getQrCodeDataUrl($code, $size);
    }

    /**
     * Send gift card email to recipient via core email queue
     *
     * @throws Mage_Core_Exception
     */
    public function sendGiftcardEmail(Maho_Giftcard_Model_Giftcard $giftcard): bool
    {
        if (!$giftcard->getRecipientEmail()) {
            throw new Mage_Core_Exception('No recipient email address.');
        }

        $storeId = Mage::app()->getStore()->getId();

        // Prepare template variables
        $vars = [
            'giftcard' => $giftcard,
            'code' => $giftcard->getCode(),
            'balance' => $this->formatAmount((float) $giftcard->getBalance(), $giftcard->getCurrencyCode()),
            'recipient_name' => $giftcard->getRecipientName() ?: 'Valued Customer',
            'sender_name' => $giftcard->getSenderName() ?: '',
            'message' => $giftcard->getMessage() ?: '',
            'qr_url' => $this->getQrCodeDataUrl($giftcard->getCode(), 300),
            'barcode_url' => $this->getBarcodeDataUrl($giftcard->getCode()),
            'store_name' => Mage::getStoreConfig('general/store_information/name', $storeId),
            'store_url' => Mage::getBaseUrl(),
        ];

        if ($giftcard->getExpiresAt()) {
            $expiryDate = new DateTime($giftcard->getExpiresAt());
            $vars['expires_at'] = $expiryDate->format('F j, Y');
        }

        try {
            // Get template ID and sender identity from configuration
            $templateId = Mage::getStoreConfig('giftcard/email/template', $storeId);
            $identity = Mage::getStoreConfig('giftcard/email/identity', $storeId) ?: 'general';

            if (!$templateId) {
                throw new Mage_Core_Exception('No email template configured. Please configure template in System > Configuration > Sales > Gift Cards > Email Settings.');
            }

            // Create email queue entry using core queue
            $emailQueue = Mage::getModel('core/email_queue');
            $emailQueue->setEntityId($giftcard->getId())
                ->setEntityType('giftcard')
                ->setEventType('giftcard_notification');

            // Use mailer to send via queue
            $mailer = Mage::getModel('core/email_template_mailer');
            $emailInfo = Mage::getModel('core/email_info');
            $emailInfo->addTo($giftcard->getRecipientEmail(), $giftcard->getRecipientName() ?: 'Valued Customer');
            $mailer->addEmailInfo($emailInfo);

            $mailer->setSender($identity)
                ->setStoreId($storeId)
                ->setTemplateId($templateId)
                ->setTemplateParams($vars)
                ->setQueue($emailQueue)
                ->send();

            // Mark email as sent on giftcard
            $giftcard->setEmailSentAt(Mage_Core_Model_Locale::now());
            $giftcard->save();

            return true;
        } catch (Exception $e) {
            Mage::logException($e);
            throw new Mage_Core_Exception('Email sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Schedule gift card email for later delivery
     *
     * @throws Mage_Core_Exception
     */
    public function scheduleGiftcardEmail(Maho_Giftcard_Model_Giftcard $giftcard, DateTime $scheduleAt): bool
    {
        if (!$giftcard->getRecipientEmail()) {
            throw new Mage_Core_Exception('No recipient email address.');
        }

        // Set scheduled time on the gift card itself
        $giftcard->setEmailScheduledAt($scheduleAt->format('Y-m-d H:i:s'));
        $giftcard->save();

        return true;
    }
}
