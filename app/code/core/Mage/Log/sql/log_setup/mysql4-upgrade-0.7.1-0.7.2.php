<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$this->run("
truncate table `{$this->getTable('log_quote')}`;
truncate table `{$this->getTable('log_url')}`;
truncate table `{$this->getTable('log_url_info')}`;
truncate table `{$this->getTable('log_visitor')}`;
truncate table `{$this->getTable('log_visitor_info')}`;
");
