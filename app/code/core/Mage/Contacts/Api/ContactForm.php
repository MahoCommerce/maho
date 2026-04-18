<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Contacts
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Contacts\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    shortName: 'ContactForm',
    description: 'Contact form submission and configuration',
    operations: [
        new Get(
            uriTemplate: '/contact/config',
            name: 'contact_config',
            provider: ContactFormProvider::class,
            security: 'true',
            description: 'Get contact form configuration (CAPTCHA provider, site key, honeypot)',
        ),
        new Post(
            uriTemplate: '/contact',
            name: 'submit_contact',
            processor: ContactFormProcessor::class,
            security: 'true',
            description: 'Submit a contact form message',
        ),
    ],
)]
class ContactForm extends \Maho\ApiPlatform\Resource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = 'contact';

    #[ApiProperty(description: 'Contact name', writable: true, readable: false)]
    public ?string $name = null;

    #[ApiProperty(description: 'Contact email', writable: true, readable: false)]
    public ?string $email = null;

    #[ApiProperty(description: 'Message body', writable: true, readable: false)]
    public ?string $comment = null;

    #[ApiProperty(description: 'Phone number', writable: true, readable: false)]
    public ?string $telephone = null;

    #[ApiProperty(description: 'CAPTCHA token from client-side widget', writable: true, readable: false)]
    public ?string $captchaToken = null;

    #[ApiProperty(description: 'Whether the contact form is enabled')]
    public bool $enabled = false;

    #[ApiProperty(description: 'CAPTCHA provider name (none, turnstile, recaptcha_v3)')]
    public ?string $captchaProvider = null;

    #[ApiProperty(description: 'CAPTCHA site key for client-side widget')]
    public ?string $captchaSiteKey = null;

    #[ApiProperty(description: 'Honeypot field name if enabled, null otherwise')]
    public ?string $honeypotField = null;

    #[ApiProperty(description: 'Whether the submission was successful')]
    public ?bool $success = null;

    #[ApiProperty(description: 'Response message')]
    public ?string $message = null;
}
