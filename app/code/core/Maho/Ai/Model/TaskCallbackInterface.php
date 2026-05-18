<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
