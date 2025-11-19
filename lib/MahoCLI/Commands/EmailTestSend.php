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
    name: 'email:test:send',
    description: 'Send a test email',
)]
class EmailTestSend extends BaseMahoCommand
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
        $emailTemplate
            ->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name'))
            ->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
            ->setTemplateType(\Mage_Core_Model_Template::TYPE_TEXT)
            ->setTemplateText('This is just a test.')
            ->setTemplateSubject('Test email');
        $retult = $emailTemplate->send($recipient, 'Test email');

        if ($retult) {
            $output->writeln('Test email successfully sent!');
            return Command::SUCCESS;
        }

        $output->writeln('Could not send test email, please check error logs.');
        return Command::FAILURE;
    }
}
