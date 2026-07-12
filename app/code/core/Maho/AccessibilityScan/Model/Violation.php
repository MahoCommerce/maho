<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

/**
 * @method int getPageId()
 * @method $this setPageId(int $value)
 * @method int getScanId()
 * @method $this setScanId(int $value)
 * @method string getAxeRuleId()
 * @method $this setAxeRuleId(string $value)
 * @method ?string getImpact()
 * @method $this setImpact(?string $value)
 * @method ?string getWcagLevel()
 * @method $this setWcagLevel(?string $value)
 * @method ?string getWcagCriteria()
 * @method $this setWcagCriteria(?string $value)
 * @method ?string getDescription()
 * @method $this setDescription(?string $value)
 * @method ?string getHelpUrl()
 * @method $this setHelpUrl(?string $value)
 * @method ?string getHtmlSnippet()
 * @method $this setHtmlSnippet(?string $value)
 * @method ?string getCssSelector()
 * @method $this setCssSelector(?string $value)
 * @method ?string getFailureSummary()
 * @method $this setFailureSummary(?string $value)
 * @method ?string getTemplateFile()
 * @method $this setTemplateFile(?string $value)
 * @method ?int getTemplateLine()
 * @method $this setTemplateLine(?int $value)
 */
class Maho_AccessibilityScan_Model_Violation extends Mage_Core_Model_Abstract
{
    public const IMPACT_CRITICAL = 'critical';
    public const IMPACT_SERIOUS  = 'serious';
    public const IMPACT_MODERATE = 'moderate';
    public const IMPACT_MINOR    = 'minor';

    /** Impact levels ordered from most to least severe */
    public const IMPACT_LEVELS = [
        self::IMPACT_CRITICAL,
        self::IMPACT_SERIOUS,
        self::IMPACT_MODERATE,
        self::IMPACT_MINOR,
    ];

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('accessibilityscan/violation');
    }
}
