<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;

/**
 * Base class for convention-based CRUD API resources.
 *
 * Extend this instead of Resource to get automatic model loading,
 * property mapping, and CRUD operations with zero boilerplate.
 *
 * Usage:
 *   #[ApiResource(
 *       provider: CrudProvider::class,
 *       operations: [new Get(uriTemplate: '/articles/{id}')],
 *   )]
 *   class Article extends CrudResource {
 *       public const MODEL = 'blog/article';
 *       public ?int $id = null;
 *       public ?string $title = null;
 *       public ?string $urlKey = null;  // maps to url_key on the model
 *   }
 *
 * Properties are mapped to model fields via camelCase to snake_case convention.
 * Types are coerced based on PHP property type declarations.
 *
 * Constants:
 *   MODEL        = 'module/model'   (required) Mage model alias
 *   PRIMARY_KEY  = 'page_id'        (optional) model PK field, auto-detected by default
 */
abstract class CrudResource extends Resource
{
    /** @var array<class-string, CrudMetadata> */
    private static array $metadataCache = [];

    /**
     * Resolve metadata for this resource class (cached).
     * Named to avoid "get*" pattern which API Platform serializer treats as a property.
     */
    public static function metadata(): CrudMetadata
    {
        $class = static::class;
        if (isset(self::$metadataCache[$class])) {
            return self::$metadataCache[$class];
        }

        $ref = new \ReflectionClass($class);

        // Read model alias from MODEL constant (preferred) or extraProperties fallback
        $model = defined("{$class}::MODEL") ? constant("{$class}::MODEL") : null;
        $primaryKey = defined("{$class}::PRIMARY_KEY") ? constant("{$class}::PRIMARY_KEY") : null;

        if (!$model) {
            // Fallback: read from ApiResource extraProperties
            $attrs = $ref->getAttributes(ApiResource::class);
            if (!empty($attrs)) {
                $extra = $attrs[0]->getArguments()['extraProperties'] ?? [];
                $model = $extra['model'] ?? null;
                $primaryKey ??= $extra['primaryKey'] ?? null;
            }
        }

        if (!$model) {
            throw new \LogicException(
                "{$class} extends CrudResource but has no model. "
                . "Add: public const MODEL = 'module/model';",
            );
        }

        // Build field mappings from public properties
        $fields = [];
        $identifierProp = null;
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->getName() === 'extensions') {
                continue;
            }

            $name = $prop->getName();
            $refType = $prop->getType();
            $type = $refType instanceof \ReflectionNamedType ? $refType->getName() : 'mixed';
            $nullable = $refType?->allowsNull() ?? true;

            $isIdentifier = false;
            $writable = true;
            $computed = false;
            $customModelField = null;
            $apiPropAttrs = $prop->getAttributes(ApiProperty::class);
            if (!empty($apiPropAttrs)) {
                $apiPropArgs = $apiPropAttrs[0]->getArguments();
                $isIdentifier = $apiPropArgs['identifier'] ?? false;
                if (isset($apiPropArgs['writable'])) {
                    $writable = $apiPropArgs['writable'];
                }
                $extraProps = $apiPropArgs['extraProperties'] ?? [];
                $computed = $extraProps['computed'] ?? false;
                $customModelField = $extraProps['modelField'] ?? null;
            }

            if ($isIdentifier) {
                $writable = false;
                $identifierProp = $name;
            }

            $fields[] = new FieldMapping(
                property: $name,
                modelField: $customModelField ?? self::camelToSnake($name),
                type: $type,
                nullable: $nullable,
                writable: $writable,
                isIdentifier: $isIdentifier,
                computed: $computed,
            );
        }

        // Auto-detect primary key: use extraProperties['primaryKey'], or model's idFieldName
        if (!$primaryKey && $identifierProp) {
            // Try to get the model's actual PK field name
            try {
                $testModel = \Mage::getModel($model);
                if ($testModel) {
                    $primaryKey = $testModel->getResource()->getIdFieldName();
                }
            } catch (\Throwable) {
                // Fallback
            }
            $primaryKey = $primaryKey ?: 'entity_id';
        }

        // If identifier property maps to 'id' but PK is different (e.g. page_id),
        // update the field mapping
        if ($identifierProp && $primaryKey) {
            foreach ($fields as $i => $field) {
                if ($field->isIdentifier && $field->modelField !== $primaryKey) {
                    $fields[$i] = new FieldMapping(
                        property: $field->property,
                        modelField: $primaryKey,
                        type: $field->type,
                        nullable: $field->nullable,
                        writable: false,
                        isIdentifier: true,
                    );
                }
            }
        }

        return self::$metadataCache[$class] = new CrudMetadata($model, $primaryKey ?: 'entity_id', $fields);
    }

    /**
     * Map a Mage model to this DTO using convention-based field mapping.
     * Called on concrete subclasses only (e.g. Article::fromModel($model)).
     */
    public static function fromModel(object $model): static
    {
        $class = static::class;
        $dto = new $class();
        foreach (static::metadata()->fields as $field) {
            if ($field->computed) {
                continue;
            }
            $value = $model->getData($field->modelField);
            $dto->{$field->property} = self::castValue($value, $field->type, $field->nullable);
        }

        // Call afterLoad() if the DTO defines it
        if (method_exists($class, 'afterLoad')) {
            $class::afterLoad($dto, $model);
        }

        return $dto;
    }

    /**
     * Filter CMS content directives ({{media}}, {{block}}, {{config}}, etc.)
     * using the core CMS template filter.
     */
    public static function filterContent(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $filter = \Mage::helper('cms')->getPageTemplateProcessor();
        $filter->setStoreId(Service\StoreContext::getStoreId());

        return $filter->filter($content);
    }

    /**
     * Apply writable DTO properties onto a Mage model.
     */
    public function applyToModel(object $model): void
    {
        foreach (static::metadata()->fields as $field) {
            if (!$field->writable) {
                continue;
            }
            $value = $this->{$field->property};
            if ($value !== null || $field->nullable) {
                $model->setData($field->modelField, $value);
            }
        }
    }

    public static function camelToSnake(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }

    private static function castValue(mixed $value, string $type, bool $nullable): mixed
    {
        if ($value === null) {
            return $nullable ? null : match ($type) {
                'int' => 0,
                'float' => 0.0,
                'bool' => false,
                'string' => '',
                'array' => [],
                default => null,
            };
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            'array' => (array) $value,
            default => $value,
        };
    }
}

/**
 * Cached metadata for a CrudResource class.
 */
final class CrudMetadata
{
    /**
     * @param list<FieldMapping> $fields
     */
    public function __construct(
        public readonly string $model,
        public readonly string $primaryKey,
        public readonly array $fields,
    ) {}
}

/**
 * Describes how a single DTO property maps to a model field.
 */
final class FieldMapping
{
    public function __construct(
        public readonly string $property,
        public readonly string $modelField,
        public readonly string $type,
        public readonly bool $nullable,
        public readonly bool $writable,
        public readonly bool $isIdentifier,
        public readonly bool $computed = false,
    ) {}
}
