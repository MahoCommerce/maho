<?php

/**
 * Email attachment descriptor: carries how to (re)generate a document, not its bytes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Symfony\Component\Mime\Email;

/**
 * The bytes are produced lazily via getContent() so an attachment can survive the email
 * queue as a compact descriptor (see toArray()/fromArray()) and be materialized only at
 * send time, avoiding storing binary blobs in the queue's TEXT column.
 */
class Mage_Core_Model_Email_Attachment
{
    protected ?string $content = null;

    public function __construct(
        protected string $sourceModel,
        protected string $entityModel,
        protected int $entityId,
        protected string $filename,
        protected string $mimeType = 'application/pdf',
    ) {}

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Materialize the attachment bytes, generating (and caching) them on first access.
     */
    public function getContent(): string
    {
        if ($this->content === null) {
            $entity = Mage::getModel($this->entityModel)->load($this->entityId);
            $renderer = Mage::getModel($this->sourceModel);
            if (!is_object($renderer) || !method_exists($renderer, 'getPdf')) {
                Mage::throwException("Invalid attachment source model: {$this->sourceModel}");
            }
            // $renderer is a dynamically-resolved PDF model implementing getPdf(array): string
            $this->content = (string) $renderer->getPdf([$entity]); // @phpstan-ignore argument.type
        }
        return $this->content;
    }

    /**
     * Attach this document to a Symfony email message.
     */
    public function applyTo(Email $message): void
    {
        $message->attach($this->getContent(), $this->filename, $this->mimeType);
    }

    /**
     * @return array{source_model: string, entity_model: string, entity_id: int, filename: string, mime_type: string}
     */
    public function toArray(): array
    {
        return [
            'source_model' => $this->sourceModel,
            'entity_model' => $this->entityModel,
            'entity_id'    => $this->entityId,
            'filename'     => $this->filename,
            'mime_type'    => $this->mimeType,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['source_model'],
            (string) $data['entity_model'],
            (int) $data['entity_id'],
            (string) $data['filename'],
            (string) ($data['mime_type'] ?? 'application/pdf'),
        );
    }

    /**
     * Rebuild attachments from their serialized descriptors and add them to the email.
     *
     * A document that fails to regenerate is logged and skipped rather than aborting the
     * whole message, so one broken attachment never blocks a transactional email.
     *
     * @param array<int, array> $descriptors
     */
    public static function applyDescriptors(Email $message, array $descriptors): void
    {
        foreach ($descriptors as $descriptor) {
            try {
                self::fromArray($descriptor)->applyTo($message);
            } catch (\Throwable $e) {
                Mage::logException($e);
            }
        }
    }
}
