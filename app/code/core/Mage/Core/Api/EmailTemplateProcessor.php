<?php

/**
 * Give an email template write to the email template service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;

final class EmailTemplateProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?EmailTemplate
    {
        $this->requireUser();
        $service = \Mage::getService('core/email_template');
        $template = isset($uriVariables['id']) ? $service->getById((int) $uriVariables['id']) : $service->newTemplate();
        $oldData = $template->getId() ? $template->getData() : null;

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($template);
            $this->logApiActivity('email_template', 'delete', $oldData, null);
            return null;
        }

        /** @var EmailTemplate $data */
        $body = $this->parseRequestBody($context['request'] ?? null);
        $data->applyToModel($template, $oldData === null ? null : array_map(strval(...), array_keys($body)));
        if ($data->templateType !== null) {
            // The service refuses the type 0, which stands for a name that is not text or html
            $template->setTemplateType(match ($data->templateType) {
                EmailTemplate::TYPE_TEXT => \Mage_Core_Model_Template::TYPE_TEXT,
                EmailTemplate::TYPE_HTML => \Mage_Core_Model_Template::TYPE_HTML,
                default => 0,
            });
        }

        $service->save($template);
        $this->logApiActivity('email_template', $oldData === null ? 'create' : 'update', $oldData, $template);

        return EmailTemplate::fromModel($template);
    }
}
