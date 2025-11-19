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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'email:test:queue',
    description: 'Send a test email via the queue system',
)]
class EmailTestQueue extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('recipient', InputArgument::REQUIRED, 'Address to send email to');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $recipient = $input->getArgument('recipient');

        $emailTemplate = Mage::getModel('core/email_template');
        $emailQueue = Mage::getModel('core/email_queue');

        $emailTemplate
            ->setQueue($emailQueue) // This forces it to use the queue
            ->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name'))
            ->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
            ->setTemplateType(\Mage_Core_Model_Template::TYPE_TEXT)
            ->setTemplateText('This is just a test.')
            ->setTemplateSubject('Test email');
        $result = $emailTemplate->send($recipient, 'Test email');

        if ($result) {
            $output->writeln('Test email successfully added to queue!');
            return Command::SUCCESS;
        }

        $output->writeln('Could not add test email to queue, please check error logs.');
        return Command::FAILURE;
    }
}
