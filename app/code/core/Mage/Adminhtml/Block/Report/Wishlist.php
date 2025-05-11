<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Report_Wishlist extends Mage_Adminhtml_Block_Template
{
    public $wishlists_count;
    public $items_bought;
    public $shared_count;
    public $referrals_count;
    public $conversions_count;
    public $customer_with_wishlist;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('report/wishlist.phtml');
    }

    #[\Override]
    public function _beforeToHtml()
    {
        $this->setChild('grid', $this->getLayout()->createBlock('adminhtml/report_wishlist_grid', 'report.grid'));

        $collection = Mage::getResourceModel('reports/wishlist_collection');

        [$customerWithWishlist, $wishlistsCount] = $collection->getWishlistCustomerCount();
        $this->setCustomerWithWishlist($customerWithWishlist);
        $this->setWishlistsCount($wishlistsCount);
        $this->setItemsBought(0);
        $this->setSharedCount($collection->getSharedCount());
        $this->setReferralsCount(0);
        $this->setConversionsCount(0);

        return $this;
    }
}
