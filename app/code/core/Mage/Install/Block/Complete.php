<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Install_Block_Complete extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/complete.phtml');
    }

    public function getLanguagePackCommand(): ?string
    {
        $session = Mage::getSingleton('install/session');
        $localization = $session->getLocalizationData();

        if (empty($localization['install_langpack'])) {
            return null;
        }

        $locale = (string) $session->getLocale();
        if (!$locale || !in_array($locale, Mage_Install_Helper_Data::AVAILABLE_LANGUAGE_PACKS, true)) {
            return null;
        }

        return 'composer require mahocommerce/maho-language-' . strtolower($locale);
    }
}
