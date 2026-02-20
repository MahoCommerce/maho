<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Helper_Security extends Mage_Core_Helper_Abstract
{
    /** @var array<int, array{block: class-string<Mage_Core_Block_Abstract>, method: string}> */
    private array $invalidBlockActions = [
        ['block' => Mage_Page_Block_Html_Topmenu_Renderer::class, 'method' => 'render'],
        ['block' => Mage_Core_Block_Template::class, 'method' => 'fetchView'],
    ];

    /**
     * @param string[] $args
     * @throws Mage_Core_Exception
     */
    public function ensureBlockMethodAllowed(Mage_Core_Block_Abstract $block, string $method, array $args): void
    {
        foreach ($this->invalidBlockActions as $action) {
            $calledMethod = strtolower($method);
            if (str_contains($calledMethod, '::')) {
                $calledMethod = explode('::', $calledMethod)[1];
            }
            if ($block instanceof $action['block'] && strtolower($action['method']) === $calledMethod) {
                Mage::throwException(
                    sprintf('Action with combination block %s and method %s is forbidden.', $block::class, $method),
                );
            }
        }
    }

    /**
     * @deprecated since 26.1, use ensureBlockMethodAllowed() instead
     */
    public function validateAgainstBlockMethodBlacklist(Mage_Core_Block_Abstract $block, string $method, array $args): void
    {
        $this->ensureBlockMethodAllowed($block, $method, $args);
    }
}
