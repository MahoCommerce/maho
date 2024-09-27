<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dataflow Batch abstract model
 *
 * @category   Mage
 * @package    Mage_Dataflow
 */
abstract class Mage_Dataflow_Model_Batch_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * Set batch data
     * automatic convert to serialize data
     *
     * @param mixed $data
     * @return Mage_Dataflow_Model_Batch_Abstract
     */
    public function setBatchData($data)
    {
        if ('"libiconv"' == ICONV_IMPL) {
            foreach ($data as &$value) {
                $value = iconv('utf-8', 'utf-8//IGNORE', $value);
            }
        }

        $this->setData('batch_data', serialize($data));

        return $this;
    }

    /**
     * Retrieve batch data
     * return unserialize data
     *
     * @return mixed
     */
    public function getBatchData()
    {
        $data = $this->_data['batch_data'];
        $data = unserialize($data, ['allowed_classes' => false]);
        return $data;
    }

    /**
     * Retrieve id collection
     *
     * @param int $batchId
     * @return array
     */
    public function getIdCollection($batchId = null)
    {
        if ($batchId !== null) {
            $this->setBatchId($batchId);
        }
        return $this->getResource()->getIdCollection($this);
    }

    public function deleteCollection($batchId = null)
    {
        if ($batchId !== null) {
            $this->setBatchId($batchId);
        }
        return $this->getResource()->deleteCollection($this);
    }
}
