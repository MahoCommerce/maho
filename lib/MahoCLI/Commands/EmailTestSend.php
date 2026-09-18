<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'email:test:send',
    description: 'Send a test email',
)]
class EmailTestSend extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Address to send email to')]
        string $recipient,
    ): int {
        $this->initMaho();
        // Send immediately instead of queueing, so transport errors surface right here
        Mage::setIsDeveloperMode(true);

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
