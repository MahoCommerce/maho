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
use Maho\ApiPlatform\Trait\ModelPersistenceTrait;
use Maho\ApiPlatform\Trait\RateLimitTrait;
use Maho\ApiPlatform\Trait\StoreAccessTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Base class for all API state processors.
 *
 * Provides a default process() implementation that handles the common pattern:
 * - Auth + permission check
 * - Delete/Update/Create routing
 * - Model persistence with activity logging
 *
 * Simple processors only need to set properties and implement applyData() + buildResponse().
 * Complex processors can override process() entirely.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
abstract class Processor implements ProcessorInterface
{
    use AuthenticationTrait;
    use ModelPersistenceTrait;
    use ActivityLogTrait;
    use StoreAccessTrait;
    use RateLimitTrait;

    protected ?string $modelAlias = null;
    protected ?string $writePermission = null;
    protected ?string $deletePermission = null;
    protected ?string $entityType = null;
    protected ?string $entityLabel = null;

    public function __construct(?Security $security = null)
    {
        $this->security = $security;
    }

    /**
     * Default process() routing: delete → update → create.
     *
     * Override this entirely for resources with non-standard write logic.
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->getAuthorizedUser();

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, $this->deletePermission ?? $this->writePermission);
            return $this->processDelete((int) $uriVariables['id'], $user);
        }

        $this->requirePermission($user, $this->writePermission);

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
        $model = \Mage::getModel($this->modelAlias);
        $this->applyData($model, $data, $user);
        $this->safeSave($model, "create {$this->entityLabel}");
        $this->logApiActivity($this->entityType, 'create', null, $model, $user);
        return $this->buildResponse($model, $data);
    }

    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        $model = $this->loadOrFail($this->modelAlias, $id, ucfirst($this->entityLabel) . ' not found');
        $this->authorizeEntity($model, $user);
        $oldData = $model->getData();
        $this->applyData($model, $data, $user);
        $this->safeSave($model, "update {$this->entityLabel}");
        $this->logApiActivity($this->entityType, 'update', $oldData, $model, $user);
        return $this->buildResponse($model, $data);
    }

    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, ucfirst($this->entityLabel) . ' not found');
        $this->authorizeEntity($model, $user);
        $oldData = $model->getData();
        $this->safeDelete($model, "delete {$this->entityLabel}");
        $this->logApiActivity($this->entityType, 'delete', $oldData, null, $user);
        return null;
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
}
