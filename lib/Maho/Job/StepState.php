<?php

/**
 * State of a single step of a backgrounded job, as rendered by MahoJobDialog.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Job;

enum StepState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Success = 'success';
    case Error = 'error';
    case Skipped = 'skipped';
}
