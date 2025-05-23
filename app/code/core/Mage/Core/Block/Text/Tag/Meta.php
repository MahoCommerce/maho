<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method string getContentType()
 * @method $this setContentType(string $value)
 * @method string getTitle()
 * @method string getDescription()
 * @method string getKeywords()
 * @method string getRobots()
 */
class Mage_Core_Block_Text_Tag_Meta extends Mage_Core_Block_Text
{
    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->getContentType()) {
            $this->setContentType('text/html; charset=utf-8');
        }
        $this->addText('<meta http-equiv="Content-Type" content="' . $this->getContentType() . '"/>' . "\n");
        $this->addText('<title>' . $this->getTitle() . '</title>' . "\n");
        $this->addText('<meta name="title" content="' . $this->getTitle() . '"/>' . "\n");
        $this->addText('<meta name="description" content="' . $this->getDescription() . '"/>' . "\n");
        $this->addText('<meta name="keywords" content="' . $this->getKeywords() . '"/>' . "\n");
        $this->addText('<meta name="robots" content="' . $this->getRobots() . '"/>' . "\n");

        return parent::_toHtml();
    }
}
