<?php

/**
 * The access of an API caller to the report explorer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\Report\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait ExplorerAccessTrait
{
    /**
     * An admin token reads the datasets that its admin role allows. An API key with reports/read reads every
     * dataset. The personal data needs the customers permission, the rows behind a dataset need the permission of
     * their records (such as orders), and the store restriction of the token applies.
     */
    protected function explorerAccess(ApiUser $user): \Mage_Reports_Model_Explorer_Access
    {
        $session = \Mage::getSingleton('admin/session');
        if ($session->getUser()) {
            $base = \Mage_Reports_Model_Explorer_Access::forAdmin($session);
        } else {
            $records = [];
            foreach (\Mage::getSingleton('reports/explorer_registry')->getDatasets() as $dataset) {
                $definition = $dataset->getRecords();
                if ($definition !== null && $this->hasBackendAccess($definition->apiPermission)) {
                    $records[] = $dataset->code;
                }
            }
            $base = new \Mage_Reports_Model_Explorer_Access(null, $this->hasBackendAccess('customers'), null, $records);
        }
        $storeIds = $user->getAllowedStoreIds();
        return new \Mage_Reports_Model_Explorer_Access(
            $base->datasets,
            $base->personalData,
            $storeIds === null ? null : array_values(array_map(intval(...), $storeIds)),
            $base->records,
        );
    }

    /** The keys of a request that change a preset. */
    private const array PRESET_OVERRIDES = ['range', 'from', 'to', 'compare', 'storeIds', 'scope', 'dateField', 'limit', 'offset', 'sort'];

    /**
     * The input of an explorer operation: the query string of a GET, or the body of a QUERY.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     * @throws BadRequestHttpException when the filters are not JSON
     */
    protected function explorerInput(array $context): array
    {
        $input = ReportProviderBase::requestFilters($context);
        if (is_string($input['filters'] ?? null) && $input['filters'] !== '') {
            try {
                json_decode($input['filters'], true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BadRequestHttpException('The filters parameter must be a JSON list.');
            }
        }
        return $input;
    }

    /**
     * The report request of the input: a preset with the period and the other keys of the input, or a request.
     *
     * @param array<string, mixed> $input
     * @param list<string> $ignore keys of the input that belong to the operation, not to the request
     * @throws \Mage_Core_Exception_NoSuchEntity when the preset does not exist
     */
    protected function explorerRequest(array $input, \Mage_Reports_Model_Explorer_Access $access, array $ignore = []): Request
    {
        $input = array_diff_key($input, array_flip($ignore));
        $preset = RequestInput::of($input)->string('preset') ?? '';
        return $preset !== ''
            ? \Mage::getService('reports/explorer')->presetRequest($preset, $access, array_intersect_key($input, array_flip(self::PRESET_OVERRIDES)))
            : Request::fromArray($input);
    }
}
