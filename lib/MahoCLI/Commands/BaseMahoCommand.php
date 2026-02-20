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
use Symfony\Component\Console\Command\Command;

abstract class BaseMahoCommand extends Command
{
    protected function initMaho(): void
    {
        Mage::register('isSecureArea', true, true);
        Mage::app('admin');
    }

    public function humanReadableSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0';
        }

        $i = (int) floor(log($bytes, 1024));
        return round($bytes / 1024 ** $i, [0, 0, 2, 2, 3][$i]) . ['B', 'kB', 'MB', 'GB', 'TB'][$i];
    }
}
