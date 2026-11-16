<?php

/**
 * A class that builds one report dataset. A module names it in its config.xml.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Db\Adapter\AdapterInterface;
use Maho\Report\Definition\Dataset;

interface DatasetProvider
{
    public function build(AdapterInterface $adapter): Dataset;
}
