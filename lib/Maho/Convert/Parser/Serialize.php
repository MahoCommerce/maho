<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Convert\Parser;

class Serialize extends AbstractParser
{
    #[\Override]
    public function parse()
    {
        $this->setData(unserialize($this->getData(), ['allowed_classes' => false]));
        return $this;
    }

    #[\Override]
    public function unparse()
    {
        $this->setData(serialize($this->getData()));
        return $this;
    }
}
