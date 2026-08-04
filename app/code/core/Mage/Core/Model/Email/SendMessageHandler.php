<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Sends queued transactional emails. Transport failures throw and are retried
 * with backoff; malformed addresses are unrecoverable and fail immediately.
 */
class Mage_Core_Model_Email_SendMessageHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Core_Model_Email_SendMessage $message): void
    {
        $transport = Mage::helper('core')->getMailTransport();
        if (!$transport) {
            // Email sending is disabled: swallow, matching the old queue behavior.
            return;
        }

        try {
            $email = new Email();
            $email->subject($message->subject);
            $email->from(new Address($message->fromEmail, $message->fromName));

            foreach ($message->recipients as $recipient) {
                [$emailAddress, $name, $type] = $recipient;
                $address = new Address($emailAddress, (string) $name);

                match ((int) $type) {
                    Mage_Core_Model_Email_Queue::EMAIL_TYPE_BCC => $email->addBcc($address),
                    Mage_Core_Model_Email_Queue::EMAIL_TYPE_CC => $email->addCc($address),
                    default => $email->addTo($address),
                };
            }

            if ($message->isPlain) {
                $email->text($message->body);
            } else {
                $email->html($message->body);
            }

            if ($message->replyTo !== null) {
                $email->replyTo($message->replyTo);
            }
            if ($message->returnPath !== null) {
                $email->returnPath($message->returnPath);
            }
            foreach ($message->headers as $headerName => $headerValue) {
                $email->getHeaders()->addTextHeader($headerName, $headerValue);
            }

            Mage_Core_Model_Email_Attachment::applyDescriptors($email, $message->attachments);
        } catch (RfcComplianceException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        Mage::dispatchEvent('email_queue_send_before', [
            'mail'      => $email,
            'message'   => $message,
            'transport' => new \Maho\DataObject(),
        ]);

        (new Mailer($transport))->send($email);

        foreach ($message->recipients as $recipient) {
            [$emailAddress] = $recipient;
            Mage::dispatchEvent('email_queue_send_after', [
                'to'         => $emailAddress,
                'html'       => !$message->isPlain,
                'subject'    => $message->subject,
                'email_body' => $message->body,
            ]);
        }
    }
}
