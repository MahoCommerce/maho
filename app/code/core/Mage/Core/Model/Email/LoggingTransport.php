<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class Mage_Core_Model_Email_LoggingTransport implements TransportInterface
{
    public function __construct(
        private readonly TransportInterface $innerTransport,
    ) {}

    #[\Override]
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $logData = $this->extractLogData($message);

        try {
            $result = $this->innerTransport->send($message, $envelope);
            $logData['status'] = 'sent';
            $this->saveLog($logData);
            return $result;
        } catch (\Throwable $e) {
            $logData['status'] = 'failed';
            $logData['error_message'] = mb_substr($e->getMessage(), 0, 65535);
            $this->saveLog($logData);
            throw $e;
        }
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->innerTransport;
    }

    private function extractLogData(RawMessage $message): array
    {
        $data = [
            'subject'      => '',
            'email_to'     => '',
            'email_from'   => '',
            'email_cc'     => null,
            'email_bcc'    => null,
            'template'     => null,
            'content_type' => 'text',
            'email_body'   => '',
            'status'       => 'sent',
            'error_message' => null,
        ];

        if (!$message instanceof Email) {
            $data['email_body'] = $message->toString();
            return $data;
        }

        $data['subject'] = mb_substr((string) $message->getSubject(), 0, 255);
        $data['email_to'] = $this->formatAddresses($message->getTo());
        $data['email_from'] = $this->formatAddresses($message->getFrom());
        $data['email_cc'] = $this->formatAddresses($message->getCc()) ?: null;
        $data['email_bcc'] = $this->formatAddresses($message->getBcc()) ?: null;

        if ($message->getHtmlBody()) {
            $data['content_type'] = 'html';
            $data['email_body'] = $message->getHtmlBody();
        } else {
            $data['content_type'] = 'text';
            $data['email_body'] = (string) $message->getTextBody();
        }

        return $data;
    }

    /**
     * @param Address[] $addresses
     */
    private function formatAddresses(array $addresses): string
    {
        return implode(', ', array_map(
            fn(Address $addr) => $addr->getName()
                ? $addr->getName() . ' <' . $addr->getAddress() . '>'
                : $addr->getAddress(),
            $addresses,
        ));
    }

    private function saveLog(array $data): void
    {
        try {
            /** @var Mage_Core_Model_Email_Log $log */
            $log = Mage::getModel('core/email_log');
            $log->setData($data);
            $log->save();
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }
}
