<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CategoryDynamicProcess extends BaseMahoCommand
{
    protected function configure(): void
    {
        $this
            ->setName('category:dynamic:process')
            ->setDescription('Process dynamic categories')
            ->addOption(
                'category-id',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Process specific category ID only'
            )
            ->setHelp('This command processes dynamic category rules and updates product assignments.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $categoryId = $input->getOption('category-id');
        $processor = Mage::getModel('catalog/category_dynamic_processor');

        if ($categoryId) {
            $output->writeln("<info>Processing dynamic category ID: {$categoryId}</info>");
            
            $category = Mage::getModel('catalog/category')->load($categoryId);
            if (!$category->getId()) {
                $output->writeln("<error>Category with ID {$categoryId} not found.</error>");
                return Command::FAILURE;
            }

            if (!$category->getIsDynamic()) {
                $output->writeln("<comment>Category {$categoryId} is not marked as dynamic.</comment>");
                return Command::SUCCESS;
            }

            try {
                $processor->processDynamicCategory($category);
                $output->writeln("<info>Successfully processed dynamic category: {$category->getName()}</info>");
            } catch (\Exception $e) {
                $output->writeln("<error>Error processing category {$categoryId}: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        } else {
            $output->writeln("<info>Processing all dynamic categories...</info>");
            
            try {
                $processor->processAllDynamicCategories();
                $output->writeln("<info>Successfully processed all dynamic categories.</info>");
            } catch (\Exception $e) {
                $output->writeln("<error>Error processing dynamic categories: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}