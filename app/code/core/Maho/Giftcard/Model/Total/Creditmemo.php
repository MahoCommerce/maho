<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Model_Total_Creditmemo extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();

        $giftcardAmount = $order->getGiftcardAmount();
        $baseGiftcardAmount = $order->getBaseGiftcardAmount();

        if ($giftcardAmount) {
            $creditmemo->setGiftcardAmount($giftcardAmount);
            $creditmemo->setBaseGiftcardAmount($baseGiftcardAmount);

            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - $giftcardAmount);
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - $baseGiftcardAmount);
        }

        return $this;
    }
}
