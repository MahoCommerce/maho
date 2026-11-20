<?php

/**
 * A ready-made report: a request on one dataset with a name and a chart.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

final class Preset
{
    /**
     * @param string $group the label of the group that lists the preset on the page
     * @param array<string, mixed> $request the request, in the shape of {@see \Maho\Report\Request::fromArray()}
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $group,
        public readonly array $request,
        public readonly ChartType $chart = ChartType::Line,
        public readonly ?string $description = null,
    ) {}
}
