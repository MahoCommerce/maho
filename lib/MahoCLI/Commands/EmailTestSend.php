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
        try {
            /** @var \Mage_Core_Service_Email_Template $service */
            $service = Mage::getService('core/email_template');
            $service->sendTest($recipient);
        } catch (\Mage_Core_Exception_Input $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::INVALID;
        } catch (\Mage_Core_Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
        $output->writeln('Test email successfully sent!');
        return Command::SUCCESS;
    }
}
