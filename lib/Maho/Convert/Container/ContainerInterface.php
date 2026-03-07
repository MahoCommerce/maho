<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Convert\Container;

use Maho\Convert\Exception;
use Maho\Convert\Profile\AbstractProfile;

interface ContainerInterface
{
    public function getData(): mixed;

    public function setData(mixed $data): self;

    public function setProfile(AbstractProfile $profile): self;

    public function addException(string $error, string|int|null $level = null): Exception;

    public function setVars(array $vars): self;
}
