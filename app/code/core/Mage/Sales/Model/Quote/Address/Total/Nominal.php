<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Nominal items total
 * Collects only items segregated by isNominal property
 * Aggregates row totals per item
 */
class Mage_Sales_Model_Quote_Address_Total_Nominal extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    /**
     * Invoke collector for nominal items
     *
     * @return Mage_Sales_Model_Quote_Address_Total_Nominal
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $collector = Mage::getSingleton(
            'sales/quote_address_total_nominal_collector',
            ['store' => $address->getQuote()->getStore()],
        );

        // invoke nominal totals
        foreach ($collector->getCollectors() as $model) {
            $model->collect($address);
        }

        // aggregate collected amounts into one to have sort of grand total per item
        foreach ($address->getAllNominalItems() as $item) {
            $rowTotal = 0;
            $baseRowTotal = 0;
            $totalDetails = [];
            foreach ($collector->getCollectors() as $model) {
                $itemRowTotal = $model->getItemRowTotal($item);
                if ($model->getIsItemRowTotalCompoundable($item)) {
                    $rowTotal += $itemRowTotal;
                    $baseRowTotal += $model->getItemBaseRowTotal($item);
                    $isCompounded = true;
                } else {
                    $isCompounded = false;
                }
                if ((float) $itemRowTotal > 0 && $label = $model->getLabel()) {
                    $totalDetails[] = new \Maho\DataObject([
                        'label'  => $label,
                        'amount' => $itemRowTotal,
                        'is_compounded' => $isCompounded,
                    ]);
                }
            }
            $item->setNominalRowTotal($rowTotal);
            $item->setBaseNominalRowTotal($baseRowTotal);
            $item->setNominalTotalDetails($totalDetails);
        }

        return $this;
    }

    /**
     * Fetch collected nominal items
     *
     * @return $this
     */
    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $items = $address->getAllNominalItems();
        if ($items) {
            $address->addTotal([
                'code'    => $this->getCode(),
                'title'   => Mage::helper('sales')->__('Nominal Items'),
                'items'   => $items,
                'area'    => 'footer',
            ]);
        }
        return $this;
    }
}
