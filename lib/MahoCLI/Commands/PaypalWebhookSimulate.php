<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dev:paypal:webhook:simulate',
    description: 'Simulate a PayPal webhook event for testing',
)]
class PaypalWebhookSimulate extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('event_type', InputArgument::REQUIRED, 'Webhook event type (e.g., PAYMENT.CAPTURE.COMPLETED)')
            ->addOption('resource-id', null, InputOption::VALUE_REQUIRED, 'Resource ID to include in payload', 'SIMULATED-' . time())
            ->addOption('order-id', null, InputOption::VALUE_REQUIRED, 'PayPal order ID')
            ->addOption('invoice-id', null, InputOption::VALUE_REQUIRED, 'Mage order increment ID (invoice_id)')
            ->addOption('amount', null, InputOption::VALUE_REQUIRED, 'Amount value', '10.00')
            ->addOption('currency', null, InputOption::VALUE_REQUIRED, 'Currency code', 'USD');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $eventType = $input->getArgument('event_type');
        $resourceId = $input->getOption('resource-id');
        $orderId = $input->getOption('order-id');
        $invoiceId = $input->getOption('invoice-id');
        $amount = $input->getOption('amount');
        $currency = $input->getOption('currency');

        $payload = [
            'id' => 'WH-SIM-' . uniqid(),
            'event_type' => $eventType,
            'resource_type' => $this->_guessResourceType($eventType),
            'summary' => "Simulated {$eventType} event",
            'resource' => [
                'id' => $resourceId,
                'status' => $this->_guessStatus($eventType),
                'amount' => [
                    'value' => $amount,
                    'currency_code' => $currency,
                ],
            ],
            'create_time' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        if ($orderId) {
            $payload['resource']['supplementary_data']['related_ids']['order_id'] = $orderId;
        }

        if ($invoiceId) {
            $payload['resource']['invoice_id'] = $invoiceId;
        }

        $output->writeln("<info>Simulating webhook event: {$eventType}</info>");
        $output->writeln('<comment>Payload: ' . json_encode($payload, JSON_PRETTY_PRINT) . '</comment>');

        try {
            /** @var \Maho_Paypal_Model_Webhook_Processor $processor */
            $processor = Mage::getModel('paypal/webhook_processor');
            $processor->processUnsafe($payload);

            $output->writeln('<info>Webhook event processed successfully.</info>');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function _guessResourceType(string $eventType): string
    {
        return match (true) {
            str_starts_with($eventType, 'PAYMENT.CAPTURE') => 'capture',
            str_starts_with($eventType, 'PAYMENT.AUTHORIZATION') => 'authorization',
            str_starts_with($eventType, 'CUSTOMER.DISPUTE') => 'dispute',
            str_starts_with($eventType, 'VAULT.PAYMENT-TOKEN') => 'payment_token',
            str_starts_with($eventType, 'CHECKOUT.ORDER') => 'checkout-order',
            default => 'unknown',
        };
    }

    private function _guessStatus(string $eventType): string
    {
        return match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => 'COMPLETED',
            'PAYMENT.CAPTURE.PENDING' => 'PENDING',
            'PAYMENT.CAPTURE.DECLINED' => 'DECLINED',
            'PAYMENT.CAPTURE.REFUNDED' => 'REFUNDED',
            'PAYMENT.CAPTURE.REVERSED' => 'REVERSED',
            'PAYMENT.AUTHORIZATION.CREATED' => 'CREATED',
            'PAYMENT.AUTHORIZATION.VOIDED' => 'VOIDED',
            'CUSTOMER.DISPUTE.CREATED' => 'OPEN',
            'CUSTOMER.DISPUTE.UPDATED' => 'UNDER_REVIEW',
            'CUSTOMER.DISPUTE.RESOLVED' => 'RESOLVED',
            default => 'UNKNOWN',
        };
    }
}
