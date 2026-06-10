<?php

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Db\Platform;

use Doctrine\DBAL\Platforms\Keywords\MariaDB117Keywords;

/**
 * MariaDB keyword list extended with reserved words DBAL does not know yet.
 *
 * MariaDB 12.3 implements the Oracle-compatible TO_DATE() function as a
 * parser-level keyword (MDEV-19683), so a bare to_date column identifier is a
 * syntax error there. Listing it here makes DBAL backtick-quote it in all
 * generated DDL, on every MariaDB version that resolves to this list.
 *
 * The KeywordList feature is deprecated upstream because DBAL 5 will quote
 * every identifier unconditionally, which subsumes this fix; until then this
 * is the supported extension point.
 */
class MariaDbKeywords extends MariaDB117Keywords
{
    #[\Override]
    protected function getKeywords(): array
    {
        return array_merge(parent::getKeywords(), ['TO_DATE']);
    }
}
