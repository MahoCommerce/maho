<?php

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sys:locales',
    description: 'Get all available locales'
)]
class SysLocales extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $locales = Mage::app()->getLocale()->getOptionLocales();

        $table = new Table($output);
        $table->setHeaders(['code', 'description']);
        foreach ($locales as $locale) {
            $table->addRow([
                $locale['value'],
                $locale['label']
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
