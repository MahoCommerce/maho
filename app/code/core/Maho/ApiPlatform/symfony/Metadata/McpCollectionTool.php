<?php

/**
 * An MCP tool whose operation returns a collection.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\McpTool;

/**
 * `McpTool` extends `HttpOperation` directly, so a tool derived from a
 * `GetCollection` loses the one thing that distinguishes it: the marker
 * interface. Everything downstream branches on it, Maho's own `Provider` picks
 * `provideCollection()` over `provideItem()`, the serializer builds a Hydra
 * collection, and API Platform's MCP schema factory keeps `@id` in the advertised
 * output schema. Without this subclass a `*_list` tool silently degrades into an
 * item fetch for id 0 and answers "Not Found".
 *
 * Every `instanceof McpTool` check upstream (registry loader, operation factory,
 * structured-content processor) accepts the subclass.
 */
final class McpCollectionTool extends McpTool implements CollectionOperationInterface {}
