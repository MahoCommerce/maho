<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

trait AdminUserCommandTrait
{
    /**
     * Give the admin user service to $action. Write $success, or the message of each error that the
     * service throws, and return the exit code.
     *
     * @param callable(\Mage_Admin_Service_User): mixed $action
     */
    private function runUserAction(OutputInterface $output, callable $action, string $success): int
    {
        try {
            /** @var \Mage_Admin_Service_User $service */
            $service = Mage::getService('admin/user');
            $action($service);
        } catch (\Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $output->writeln("<error>{$error['message']}</error>");
            }
            return Command::INVALID;
        } catch (\Mage_Core_Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>{$success}</info>");
        return Command::SUCCESS;
    }
}
