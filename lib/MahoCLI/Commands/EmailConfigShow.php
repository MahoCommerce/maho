<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

#[AsCommand(
    name: 'email:config:show',
    description: 'Show email configuration and settings',
)]
class EmailConfigShow extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        // Transport Configuration
        $table = new Table($output);
        $table->setHeaders([
            [new TableCell('<info>EMAIL CONFIGURATION</info>', ['colspan' => 2])],
        ]);

        // Basic settings
        $emailDisabled = Mage::getStoreConfigFlag('system/smtp/disable');
        $dsn = Mage::helper('core')->getMailerDsn();
        $transportType = Mage::getStoreConfig('system/smtp/transport') ?: 'sendmail';

        // Parse DSN to get more details
        $transportDetails = 'Not configured';
        if ($dsn) {
            try {
                $parsed = parse_url($dsn);
                $scheme = $parsed['scheme'] ?? '';
                $host = $parsed['host'] ?? '';
                $port = $parsed['port'] ?? '';
                $user = $parsed['user'] ?? '';

                $transportDetails = $scheme;
                if ($host) {
                    $transportDetails .= "://{$host}";
                    if ($port) {
                        $transportDetails .= ":{$port}";
                    }
                }
                if ($user) {
                    $transportDetails .= " (user: {$user})";
                }
            } catch (\Exception $e) {
                $transportDetails = $dsn;
            }
        }

        $table->addRows([
            ['Email Sending', $emailDisabled ? 'Disabled' : ($dsn ? 'Enabled' : 'Disabled (no DSN)')],
            ['Transport Type', $transportType],
            ['Transport DSN', $dsn ?: 'Not configured'],
            ['Transport Details', $transportDetails],
        ]);

        // SMTP specific settings if using SMTP
        if (str_contains($transportType, 'smtp')) {
            $table->addRows([
                ['SMTP Host', Mage::getStoreConfig('system/smtp/host') ?: 'Not set'],
                ['SMTP Port', Mage::getStoreConfig('system/smtp/port') ?: 'Default'],
                ['SMTP Username', Mage::getStoreConfig('system/smtp/username') ? '***' : 'Not set'],
                ['SMTP Password', Mage::getStoreConfig('system/smtp/password') ? '***' : 'Not set'],
            ]);
        }

        // Return path settings
        $setReturnPath = Mage::getStoreConfig('system/smtp/set_return_path');
        $returnPathEmail = Mage::getStoreConfig('system/smtp/return_path_email');
        $returnPathSetting = match ($setReturnPath) {
            '1' => 'Use sender email',
            '2' => $returnPathEmail ?: 'Custom (not set)',
            default => 'No',
        };

        $table->addRow(['Return Path', $returnPathSetting]);

        // Queue System Information
        $queueCollection = Mage::getResourceModel('core/email_queue_collection');
        $pendingCount = clone $queueCollection;
        $pendingCount = $pendingCount->addFieldToFilter('processed_at', ['null' => true])->getSize();

        // Add separator row for queue section
        $table->addRow(['', '']);
        $table->addRows([
            ['Currently Pending', $pendingCount . ' emails'],
            ['Process Schedule', 'Every minute (*/1 * * * *)'],
            ['Cleanup Schedule', 'Daily at midnight (0 0 * * *)'],
        ]);

        // Add separator row for developer settings
        $table->addRow(['', '']);
        $table->addRows([
            ['Copy To', Mage::getStoreConfig('system/email/copy_to') ?: 'Not set'],
            ['Copy Method', Mage::getStoreConfig('system/email/copy_method') ?: 'Bcc'],
        ]);

        $table->render();

        // Email Identities
        $output->writeln('');
        $identityTable = new Table($output);
        $identityTable->setHeaders([
            [new TableCell('<info>EMAIL IDENTITIES</info>', ['colspan' => 3])],
            ['Identity', 'Name', 'Email'],
        ]);

        $identities = [
            'general' => 'General Contact',
            'sales' => 'Sales Representative',
            'support' => 'Customer Support',
            'custom1' => 'Custom Email 1',
            'custom2' => 'Custom Email 2',
        ];

        foreach ($identities as $code => $label) {
            $name = Mage::getStoreConfig("trans_email/ident_{$code}/name");
            $email = Mage::getStoreConfig("trans_email/ident_{$code}/email");

            if ($name || $email) {
                $identityTable->addRow([$label, $name ?: '(not set)', $email ?: '(not set)']);
            }
        }

        $identityTable->render();

        return Command::SUCCESS;
    }
}
