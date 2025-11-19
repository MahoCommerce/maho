<?php

/**
 * Maho
 *
 * @package    Mage_Tag
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Tag_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ADDING_TAGS_ENABLED_ON_FRONTEND = 'catalog/tags/enable_adding_tags_in_frontend';

    protected $_moduleName = 'Mage_Tag';

    /**
     * @return array
     */
    public function getStatusesArray()
    {
        return [
            Mage_Tag_Model_Tag::STATUS_DISABLED => Mage::helper('tag')->__('Disabled'),
            Mage_Tag_Model_Tag::STATUS_PENDING  => Mage::helper('tag')->__('Pending'),
            Mage_Tag_Model_Tag::STATUS_APPROVED => Mage::helper('tag')->__('Approved'),
        ];
    }

    /**
     * @return array
     */
    public function getStatusesOptionsArray()
    {
        return [
            [
                'label' => Mage::helper('tag')->__('Disabled'),
                'value' => Mage_Tag_Model_Tag::STATUS_DISABLED,
            ],
            [
                'label' => Mage::helper('tag')->__('Pending'),
                'value' => Mage_Tag_Model_Tag::STATUS_PENDING,
            ],
            [
                'label' => Mage::helper('tag')->__('Approved'),
                'value' => Mage_Tag_Model_Tag::STATUS_APPROVED,
            ],
        ];
    }

    /**
     * Check tags on the correctness of symbols and split string to array of tags
     *
     * @param string $tagNamesInString
     * @return array
     */
    public function extractTags($tagNamesInString)
    {
        return explode("\n", preg_replace("/(\'(.*?)\')|(\s+)/i", "$1\n", $tagNamesInString));
    }

    /**
     * Clear tag from the separating characters
     *
     * @return array
     */
    public function cleanTags(array $tagNamesArr)
    {
        foreach (array_keys($tagNamesArr) as $key) {
            $tagNamesArr[$key] = trim($tagNamesArr[$key], '\'');
            $tagNamesArr[$key] = trim($tagNamesArr[$key]);
            if ($tagNamesArr[$key] == '') {
                unset($tagNamesArr[$key]);
            }
        }
        return $tagNamesArr;
    }

    public function isAddingTagsEnabledOnFrontend(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ADDING_TAGS_ENABLED_ON_FRONTEND);
    }
}
