<?php

/**
 * Maho
 *
 * @package    Maho_LegacyMigrateTestA
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_LegacyMigrateTestA_Sub_FooController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setBody("legacymigratetesta/sub_foo/index\n");
    }

    public function barAction(): void
    {
        $this->getResponse()
            ->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setBody("legacymigratetesta/sub_foo/bar\n");
    }
}
