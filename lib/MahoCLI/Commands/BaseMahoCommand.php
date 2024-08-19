<?php

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Command\Command;

abstract class BaseMahoCommand extends Command
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

        Mage::register('isSecureArea', true);
        Mage::app();
    }

    public function humanReadableSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0';
        }

        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), [0, 0, 2, 2, 3][$i]) . ['B', 'kB', 'MB', 'GB', 'TB'][$i];
    }
}
