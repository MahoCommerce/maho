<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Maho_Validator
{
    private static ?ValidatorInterface $validator = null;

    private static function getValidator(): ValidatorInterface
    {
        if (self::$validator === null) {
            self::$validator = Validation::createValidator();
        }
        return self::$validator;
    }

    public static function validateEmail(#[\SensitiveParameter] string $email): bool
    {
        $violations = self::getValidator()->validate($email, new Assert\Email());
        return count($violations) === 0;
    }

    public static function validateNotBlank(mixed $value): bool
    {
        $violations = self::getValidator()->validate($value, new Assert\NotBlank());
        return count($violations) === 0;
    }

    public static function validateRegex(string $value, string $pattern): bool
    {
        $violations = self::getValidator()->validate($value, new Assert\Regex(['pattern' => $pattern]));
        return count($violations) === 0;
    }

    public static function validateLength(string $value, ?int $min = null, ?int $max = null): bool
    {
        $options = [];
        if ($min !== null) {
            $options['min'] = $min;
        }
        if ($max !== null) {
            $options['max'] = $max;
        }

        $violations = self::getValidator()->validate($value, new Assert\Length($options));
        return count($violations) === 0;
    }

    public static function validateRange(mixed $value, int|float|null $min = null, int|float|null $max = null): bool
    {
        $options = [];
        if ($min !== null) {
            $options['min'] = $min;
        }
        if ($max !== null) {
            $options['max'] = $max;
        }

        $violations = self::getValidator()->validate($value, new Assert\Range($options));
        return count($violations) === 0;
    }

    public static function validateUrl(string $url): bool
    {
        $violations = self::getValidator()->validate($url, new Assert\Url());
        return count($violations) === 0;
    }

    public static function validateDate(string $date): bool
    {
        $violations = self::getValidator()->validate($date, new Assert\Date());
        return count($violations) === 0;
    }

    public static function validate(mixed $value, mixed $constraint): array
    {
        $violations = self::getValidator()->validate($value, $constraint);
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages;
    }

    public static function isValid(mixed $value, mixed $constraint): bool
    {
        $violations = self::getValidator()->validate($value, $constraint);
        return count($violations) === 0;
    }
}
