<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Exception\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EmailTemplateProvider extends CrudProvider
{
    #[\Override]
    protected array $defaultSort = ['template_code' => 'ASC'];

    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        return match ($name) {
            'list_default_email_templates' => $this->listDefaults($context),
            'get_default_email_template' => $this->getDefault((string) ($uriVariables['code'] ?? '')),
            default => null,
        };
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);

        $search = $this->stringFilter($filters, 'search');
        if ($search !== null) {
            $collection->addFieldToFilter(
                ['template_code', 'template_subject'],
                [
                    ['like' => "%{$search}%"],
                    ['like' => "%{$search}%"],
                ],
            );
        }

        $type = $this->stringFilter($filters, 'templateType');
        if ($type !== null) {
            if (!in_array($type, [EmailTemplate::TYPE_TEXT, EmailTemplate::TYPE_HTML], true)) {
                throw ValidationException::invalidValue('templateType', 'must be "text" or "html"');
            }
            $collection->addFieldToFilter('template_type', EmailTemplate::typeToInt($type));
        }
    }

    /**
     * @return TraversablePaginator<EmailTemplate>
     */
    private function listDefaults(array $context): TraversablePaginator
    {
        $codes = array_keys(\Mage_Core_Model_Email_Template::getDefaultTemplates());
        sort($codes);

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 100, 500);
        $pageCodes = array_slice($codes, ($page - 1) * $pageSize, $pageSize);

        $items = array_map($this->defaultToDto(...), $pageCodes);

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, count($codes));
    }

    private function getDefault(string $code): EmailTemplate
    {
        if (!isset(\Mage_Core_Model_Email_Template::getDefaultTemplates()[$code])) {
            throw new NotFoundHttpException("Default email template '{$code}' not found");
        }

        return $this->defaultToDto($code);
    }

    private function defaultToDto(string $code): EmailTemplate
    {
        $model = \Mage::getModel('core/email_template')->loadDefault($code);

        $dto = new EmailTemplate();
        $dto->id = null;
        $dto->templateCode = $code;
        $dto->origTemplateCode = $code;
        $dto->templateSubject = $model->getTemplateSubject();
        $dto->templateText = $model->getData('template_text') === null ? null : (string) $model->getData('template_text');
        $dto->templateStyles = $model->getData('template_styles') === null ? null : (string) $model->getData('template_styles');
        $dto->templateType = EmailTemplate::typeToString($model->getData('template_type'));
        $dto->origTemplateVariables = $model->getOrigTemplateVariables();

        return $dto;
    }
}
