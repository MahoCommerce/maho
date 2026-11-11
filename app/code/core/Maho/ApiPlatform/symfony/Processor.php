<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\ActivityLogTrait;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\ApiPlatform\Trait\RawResponseTrait;
use Maho\ApiPlatform\Trait\StoreAccessTrait;
use Maho\ApiPlatform\Trait\StoreRestrictionTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Base class for all API state processors.
 *
 * Provides a default process() implementation that handles the common pattern:
 * - Auth + permission check
 * - Delete/Update/Create routing
 * - Model persistence (loadModel(), saveModel(), deleteModel()) with activity logging
 *
 * Simple processors only need to set properties and implement applyData() + buildResponse().
 * Complex processors can override process() entirely.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
abstract class Processor implements ProcessorInterface
{
    use AuthenticationTrait;
    use ActivityLogTrait;
    use RawResponseTrait;
    use StoreAccessTrait;
    use StoreRestrictionTrait;

    protected ?string $modelAlias = null;
    protected ?string $entityType = null;
    protected ?string $entityLabel = null;

    public function __construct(?Security $security = null)
    {
        $this->security = $security;
    }

    /**
     * Default process() routing: delete → update → create.
     *
     * Permissions are enforced declaratively by each operation's `security:`
     * expression before this runs; no imperative permission check here.
     * Override this entirely for resources with non-standard write logic.
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->requireUser();

        if ($operation instanceof DeleteOperationInterface) {
            return $this->processDelete((int) $uriVariables['id'], $user);
        }

        if (isset($uriVariables['id'])) {
            return $this->processUpdate((int) $uriVariables['id'], $data, $user);
        }

        return $this->processCreate($data, $user);
    }

    /**
     * Set model data from the incoming DTO.
     *
     * Subclasses that use the default process() must override this.
     * Subclasses that override process() entirely can skip this.
     */
    protected function applyData(object $model, mixed $data, ApiUser $user): void
    {
        throw new \LogicException(static::class . ' must implement applyData() or override process()');
    }

    /**
     * Build the response DTO after a successful save.
     *
     * Subclasses that use the default process() must override this.
     * Subclasses that override process() entirely can skip this.
     */
    protected function buildResponse(object $model, mixed $data): mixed
    {
        throw new \LogicException(static::class . ' must implement buildResponse() or override process()');
    }

    protected function processCreate(mixed $data, ApiUser $user): mixed
    {
        $model = $this->newModel();
        $this->applyData($model, $data, $user);
        // Authorize after applyData so store-scoped defaults (e.g. an omitted
        // `stores` defaulting to all-stores) are validated, not just raw input.
        $this->authorizeEntity($model, $user);
        $this->saveModel($model);
        $this->logApiActivity($this->entityType, 'create', null, $model);
        return $this->buildResponse($model, $data);
    }

    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        $model = $this->loadModel($id);
        $this->authorizeEntity($model, $user);
        $oldData = $model->getData();
        $this->applyData($model, $data, $user);
        $this->saveModel($model);
        $this->logApiActivity($this->entityType, 'update', $oldData, $model);
        return $this->buildResponse($model, $data);
    }

    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadModel($id);
        $this->authorizeEntity($model, $user);
        $oldData = $model->getData();
        $this->deleteModel($model);
        $this->logApiActivity($this->entityType, 'delete', $oldData, null);
        return null;
    }

    /**
     * Return a new model of $modelAlias for a create. A processor whose service gives a new record its defaults
     * overrides it.
     */
    protected function newModel(): \Mage_Core_Model_Abstract
    {
        return \Mage::getModel($this->modelAlias);
    }

    /**
     * Load the model of $modelAlias for an update or a delete. A processor whose resource has a service overrides
     * loadModel(), saveModel() and deleteModel() to call the service.
     *
     * @throws \Mage_Core_Exception_NoSuchEntity
     */
    protected function loadModel(int $id): \Mage_Core_Model_Abstract
    {
        $model = \Mage::getModel($this->modelAlias);
        try {
            $model->load($id);
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            // PostgreSQL refuses an ID above the range of the column (SQLSTATE 22003), and such an ID matches no row
            if ($e->getSQLState() !== '22003') {
                throw $e;
            }
        }
        if (!$model->getId()) {
            throw new \Mage_Core_Exception_NoSuchEntity(ucfirst((string) $this->entityLabel) . ' not found');
        }
        return $model;
    }

    protected function saveModel(\Mage_Core_Model_Abstract $model): void
    {
        $model->save();
    }

    protected function deleteModel(\Mage_Core_Model_Abstract $model): void
    {
        $model->delete();
    }

    /** Hook: entity-level authorization after load (e.g. store access checks). */
    protected function authorizeEntity(object $model, ApiUser $user): void {}

    /**
     * Decode a JSON request body into an array.
     *
     * Returns an empty array when there is no request or no body, and throws
     * a 400 when the body is present but not valid JSON. Single decode path for
     * every processor that reads a raw REST payload.
     *
     * @return array<mixed>
     */
    protected function parseRequestBody(?Request $request): array
    {
        if (!$request instanceof Request) {
            return [];
        }

        $content = $request->getContent();
        if ($content === '') {
            return [];
        }

        try {
            $body = \Mage::helper('core')->jsonDecode($content);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        return is_array($body) ? $body : [];
    }

    /**
     * Bridge a raw REST body into $context['args']['input'] so handlers that
     * read GraphQL-style args work over REST too. GraphQL invocations already
     * populate args natively, so an existing non-empty input is left untouched.
     *
     * @param array<string, mixed> $context
     */
    protected function normalizeGraphQlInput(array &$context): void
    {
        if (!empty($context['args']['input'])) {
            return;
        }

        $context['args']['input'] = $this->parseRequestBody($context['request'] ?? null);
    }

    /**
     * Return the ID in a GraphQL id argument: a plain ID ("5") or an IRI ("/api/rest/v2/addresses/5").
     * Return 0 for any other value.
     */
    protected function parseGraphQlId(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('#(?:^|/)(\d+)$#', $value, $matches) ? (int) $matches[1] : 0;
    }

}
