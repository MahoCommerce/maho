<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'customer:create',
    description: 'Create a customer',
)]
class CustomerCreate extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $question = new Question('Email: ', '');
        $email = $questionHelper->ask($input, $output, $question);
        $email = trim($email);
        if (!strlen($email)) {
            $output->writeln('<error>Email cannot be empty</error>');
            return Command::INVALID;
        }

        $question = new Question('Password: ', '');
        $password = $questionHelper->ask($input, $output, $question);
        $password = trim($password);
        if (!strlen($password)) {
            $output->writeln('<error>Password cannot be empty</error>');
            return Command::INVALID;
        }

        $question = new Question('Firstname: ', '');
        $firstname = $questionHelper->ask($input, $output, $question);
        $firstname = trim($firstname);
        if (!strlen($firstname)) {
            $output->writeln('<error>Firstname cannot be empty</error>');
            return Command::INVALID;
        }

        $question = new Question('Lastname: ', '');
        $lastname = $questionHelper->ask($input, $output, $question);
        $lastname = trim($lastname);
        if (!strlen($lastname)) {
            $output->writeln('<error>Lastname cannot be empty</error>');
            return Command::INVALID;
        }

        $websites = [];
        $websitesCollection = Mage::getResourceModel('core/website_collection');
        foreach ($websitesCollection as $website) {
            $websites[$website->getId()] = "{$website->getCode()} - {$website->getName()}";
        }
        $question = new ChoiceQuestion('Website: ', $websites);
        $website = $questionHelper->ask($input, $output, $question);
        $website = explode(' - ', $website)[0];
        $website = Mage::getModel('core/website')->load($website, 'code');
        if (!$website->getId()) {
            $output->writeln('<error>Website not found</error>');
            return Command::INVALID;
        }

        $customer = Mage::getModel('customer/customer')
            ->setEmail($email)
            ->setPassword($password)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setWebsiteId($website->getId())
            ->save();

        if (!$customer->getId()) {
            $output->writeln('<error>Unable to create customer</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Customer $email create successfully.</info>");
        return Command::SUCCESS;
    }
}
