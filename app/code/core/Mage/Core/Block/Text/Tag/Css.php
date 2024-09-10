<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Base html block
 *
 * @category   Mage
 * @package    Mage_Core
 *
 * @method $this setTagName(string $value)
 * @method $this setTagParams(array $value)
 */
class Mage_Core_Block_Text_Tag_Css extends Mage_Core_Block_Text_Tag
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTagName('link');
        $this->setTagParams(['rel' => 'stylesheet', 'type' => 'text/css', 'media' => 'all']);
    }

    /**
     * @param string $href
     * @param string|null $type
     * @return Mage_Core_Block_Text_Tag_Css
     */
    public function setHref($href, $type = null)
    {
        $type = (string)$type;
        if (empty($type)) {
            $type = 'skin';
        }
        $url = Mage::getBaseUrl($type) . $href;

        return $this->setTagParam('href', $url);
    }
}
