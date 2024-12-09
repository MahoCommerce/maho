<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Review/Rating module upgrade. Both module tables must be installed.
 */

$this->startSetup();

$voteTable   = $this->getTable('rating_option_vote');
$reviewTable = $this->getTable('review');

$this->run("
DELETE FROM `{$voteTable}` WHERE `review_id` NOT IN (SELECT review_id FROM `{$reviewTable}`);
");

$this->run("
ALTER TABLE `{$voteTable}`
ADD CONSTRAINT `FK_RATING_OPTION_REVIEW_ID` FOREIGN KEY (`review_id`) REFERENCES `{$reviewTable}` (`review_id`)
ON DELETE CASCADE ON UPDATE CASCADE;
");

$this->endSetup();
