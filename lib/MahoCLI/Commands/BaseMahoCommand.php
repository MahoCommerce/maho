<?php

namespace Maho\Commands;

use Mage;
use Symfony\Component\Console\Command\Command;

class BaseMahoCommand extends Command
{
    protected function initMaho(): void
    {
        $cwd = getcwd();
        if (file_exists("$cwd/app/bootstrap.php")) {
            require "$cwd/app/bootstrap.php";
            require "$cwd/app/Mage.php";
        } else {
            require "$cwd/vendor/mahocommerce/maho/app/bootstrap.php";
            require "$cwd/vendor/mahocommerce/maho/app/Mage.php";
        }

        Mage::app();
    }
}