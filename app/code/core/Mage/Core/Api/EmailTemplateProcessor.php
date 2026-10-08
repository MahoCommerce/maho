<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class EmailTemplateProcessor extends CrudProcessor
{
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var EmailTemplate $data */
        $code = $data->templateCode === null ? null : trim($data->templateCode);

        if ($isNew && ($code === null || $code === '')) {
            throw ValidationException::requiredField('templateCode');
        }
        if ($code !== null && $code === '') {
            throw ValidationException::invalidValue('templateCode', 'must not be empty');
        }
        if ($code !== null && $this->codeInUse($code, $isNew ? null : (int) $model->getId())) {
            throw new ConflictHttpException("An email template with code '{$code}' already exists");
        }

        if ($isNew && ($data->templateText === null || trim($data->templateText) === '')) {
            throw ValidationException::requiredField('templateText');
        }
        if (!$isNew && $data->templateText !== null && trim($data->templateText) === '') {
            throw ValidationException::invalidValue('templateText', 'must not be empty');
        }
        if ($isNew && ($data->templateSubject === null || trim($data->templateSubject) === '')) {
            throw ValidationException::requiredField('templateSubject');
        }
        if (!$isNew && $data->templateSubject !== null && trim($data->templateSubject) === '') {
            throw ValidationException::invalidValue('templateSubject', 'must not be empty');
        }

        if ($data->templateType !== null
            && !in_array($data->templateType, [EmailTemplate::TYPE_TEXT, EmailTemplate::TYPE_HTML], true)
        ) {
            throw ValidationException::invalidValue('templateType', 'must be "text" or "html"');
        }

        if ($data->templateSenderEmail !== null && $data->templateSenderEmail !== ''
            && !\Mage::helper('core')->isValidEmail($data->templateSenderEmail)
        ) {
            throw ValidationException::invalidValue('templateSenderEmail', 'is not a valid email address');
        }
    }

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        /** @var EmailTemplate $data */
        $now = \Mage::app()->getLocale()->formatDateForDb('now');

        if (!$model->getId()) {
            $model->setData('added_at', $now);
            $model->setData('template_type', EmailTemplate::typeToInt($data->templateType ?? EmailTemplate::TYPE_HTML));
        } elseif ($data->templateType !== null) {
            $model->setData('template_type', EmailTemplate::typeToInt($data->templateType));
        }

        if ($data->templateCode !== null) {
            $model->setData('template_code', trim($data->templateCode));
        }
    }

    private function codeInUse(string $code, ?int $excludeId): bool
    {
        $collection = \Mage::getModel('core/email_template')->getCollection()
            ->addFieldToFilter('template_code', $code);
        if ($excludeId !== null) {
            $collection->addFieldToFilter('template_id', ['neq' => $excludeId]);
        }

        return $collection->getSize() > 0;
    }
}
