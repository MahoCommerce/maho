<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

/**
 * Implemented by classes that handle async task completion callbacks.
 *
 * Submitters set `callback_class` / `callback_method` on a task row; the
 * TaskRunner instantiates `callback_class` after the AI provider returns
 * and invokes `callback_method`. Requiring this interface ensures only
 * classes explicitly marked as task callbacks can be instantiated by the
 * runner — closing off arbitrary-class instantiation via a crafted task
 * row.
 */
interface Maho_Ai_Model_TaskCallbackInterface {}
