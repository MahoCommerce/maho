<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Dataflow_Model_Convert_Exception extends \Maho\Exception
{
    public const NOTICE = 'NOTICE';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const FATAL = 'FATAL';

    protected $_container;

    protected $_level;

    protected $_position;

    public function setContainer($container)
    {
        $this->_container = $container;
        return $this;
    }

    public function getContainer()
    {
        return $this->_container;
    }

    public function getLevel()
    {
        return $this->_level;
    }

    public function setLevel($level)
    {
        $this->_level = $level;
        return $this;
    }

    public function getPosition()
    {
        return $this->_position;
    }

    public function setPosition($position)
    {
        $this->_position = $position;
        return $this;
    }
}
