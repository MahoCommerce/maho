<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

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

        $transportType = Mage::getStoreConfig('system/smtp/enabled');
        if ($transportType == 0) {
            $table->addRow(['Email Sending', 'Disabled']);
        } elseif ($transportType === 'sendmail') {
            $table->addRows([
                ['Email Sending', 'Enabled'],
                ['Transport', $transportType],
            ]);
        } else {
            $table->addRows([
                ['Email Sending', 'Enabled'],
                ['Transport', $transportType],
                ['SMTP Host', Mage::getStoreConfig('system/smtp/host') ?: 'Not set'],
                ['SMTP Port', Mage::getStoreConfig('system/smtp/port') ?: 'Not set'],
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

        // Get cron schedules dynamically
        $processSchedule = Mage::getConfig()->getNode('crontab/jobs/core_email_queue_send_all/schedule/cron_expr');
        $cleanupSchedule = Mage::getConfig()->getNode('crontab/jobs/core_email_queue_clean_up/schedule/cron_expr');

        // Add separator row for queue section
        $table->addRow(['', '']);
        $table->addRows([
            ['Currently Pending', $pendingCount . ' emails'],
            ['Process Schedule', $processSchedule ? (string) $processSchedule : 'Not configured'],
            ['Cleanup Schedule', $cleanupSchedule ? (string) $cleanupSchedule : 'Not configured'],
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
