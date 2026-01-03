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

class Maho_Giftcard_Adminhtml_Giftcard_PrintController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'giftcard/manage';

    /**
     * Print gift card as PDF
     */
    public function pdfAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $giftcard = Mage::getModel('giftcard/giftcard')->load($id);

        if (!$giftcard->getId()) {
            $this->_getSession()->addError('Gift card not found.');
            $this->_redirect('*/giftcard/index');
            return;
        }

        try {
            $pdf = Mage::getModel('giftcard/pdf_giftcard')->getPdf([$giftcard]);

            $this->_prepareDownloadResponse(
                'giftcard_' . $giftcard->getCode() . '.pdf',
                $pdf,
                'application/pdf',
            );
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError('Failed to generate PDF: ' . $e->getMessage());
            $this->_redirect('*/giftcard/edit', ['id' => $id]);
        }
    }

    /**
     * Print multiple gift cards as PDF
     */
    public function massPdfAction(): void
    {
        $giftcardIds = $this->getRequest()->getParam('giftcard');

        if (!is_array($giftcardIds) || $giftcardIds === []) {
            $this->_getSession()->addError('Please select gift card(s).');
            $this->_redirect('*/giftcard/index');
            return;
        }

        try {
            $giftcards = [];
            foreach ($giftcardIds as $id) {
                $giftcard = Mage::getModel('giftcard/giftcard')->load($id);
                if ($giftcard->getId()) {
                    $giftcards[] = $giftcard;
                }
            }

            if ($giftcards === []) {
                throw new Exception('No valid gift cards found.');
            }

            $pdf = Mage::getModel('giftcard/pdf_giftcard')->getPdf($giftcards);

            $this->_prepareDownloadResponse(
                'giftcards_' . date('Y-m-d') . '.pdf',
                $pdf,
                'application/pdf',
            );
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError('Failed to generate PDF: ' . $e->getMessage());
            $this->_redirect('*/giftcard/index');
        }
    }

    /**
     * Email gift card to recipient
     */
    public function emailAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $scheduleAt = $this->getRequest()->getParam('schedule_at');

        $giftcard = Mage::getModel('giftcard/giftcard')->load($id);

        if (!$giftcard->getId()) {
            $this->_getSession()->addError('Gift card not found.');
            $this->_redirect('*/giftcard/index');
            return;
        }

        if (!$giftcard->getRecipientEmail()) {
            $this->_getSession()->addError('No recipient email address set.');
            $this->_redirect('*/giftcard/edit', ['id' => $id]);
            return;
        }

        try {
            if ($scheduleAt) {
                // Schedule email for later
                $scheduleDate = new DateTime($scheduleAt);
                Mage::helper('giftcard')->scheduleGiftcardEmail($giftcard, $scheduleDate);
                $this->_getSession()->addSuccess(
                    sprintf('Gift card email scheduled for %s', $scheduleDate->format('Y-m-d H:i')),
                );
            } else {
                // Send immediately
                Mage::helper('giftcard')->sendGiftcardEmail($giftcard);
                $this->_getSession()->addSuccess('Gift card email sent successfully.');
            }

            $this->_redirect('*/giftcard/edit', ['id' => $id]);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError('Failed to send email: ' . $e->getMessage());
            $this->_redirect('*/giftcard/edit', ['id' => $id]);
        }
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('giftcard/manage');
    }
}
