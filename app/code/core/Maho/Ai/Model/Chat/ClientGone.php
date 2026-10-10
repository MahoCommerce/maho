<?php

/**
 * Raised by the SSE writer when the browser closed the stream, so the turn ends and the lock opens.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Chat_ClientGone extends RuntimeException {}
