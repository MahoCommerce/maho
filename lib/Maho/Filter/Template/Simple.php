<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Filter\Template;

use Maho\DataObject;

/**
 * Class Simple
 */
class Simple extends DataObject
{
    /**
     * Start tag for variable in template
     *
     * @var string
     */
    protected $_startTag = '{{';

    /**
     * End tag for variable in template
     *
     * @var string
     */
    protected $_endTag = '}}';

    /**
     * Define start tag and end tag
     *
     * @param string $start
     * @param string $end
     * @return Simple
     */
    public function setTags($start, $end)
    {
        $this->_startTag = $start;
        $this->_endTag = $end;
        return $this;
    }

    /**
     * Return result of getData method for matched variables
     *
     * @param array $matches
     * @return mixed
     */
    protected function _filterDataItem($matches)
    {
        return $this->getData($matches[1]);
    }

    /**
     * Insert data to template
     *
     * @param string $value
     * @return string
     */
    public function filter($value)
    {
        return preg_replace_callback(
            '#' . $this->_startTag . '(.*?)' . $this->_endTag . '#',
            [$this, '_filterDataItem'],
            $value,
        );
    }
}
