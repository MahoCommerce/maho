<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Install_Block_SampleData extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/sampledata.phtml');
    }

    /**
     * Get URL for starting sample data installation
     */
    public function getPostUrl(): string
    {
        return $this->getUrl('*/*/sampledataPost');
    }

    /**
     * Get URL for checking installation progress
     */
    public function getProgressUrl(): string
    {
        return $this->getUrl('*/*/sampledataProgress');
    }

    /**
     * Get URL for skipping sample data installation
     */
    public function getSkipUrl(): string
    {
        return $this->getUrl('*/*/sampledataSkip');
    }

    /**
     * Get URL for the next step (Administrator)
     */
    public function getNextStepUrl(): string
    {
        $step = $this->getWizard()->getStepByName('sampledata');
        return $step ? $step->getNextUrl() : $this->getUrl('*/*/administrator');
    }

    /**
     * Get the sample data installer model
     */
    public function getSampleDataInstaller(): Mage_Install_Model_Installer_SampleData
    {
        return Mage::getSingleton('install/installer_sampleData');
    }

    /**
     * Check if sample data installation is currently in progress
     */
    public function isInstalling(): bool
    {
        return $this->getSampleDataInstaller()->isInstalling();
    }
}
