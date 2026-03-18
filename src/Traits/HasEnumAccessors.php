<?php

namespace Lvntr\StarterKit\Traits;

use Lvntr\StarterKit\Enums\Contracts\HasDefinition;

/**
 * Automatically provides {field}_label and {field}_severity accessors
 * for any model attribute cast to an enum implementing HasDefinition.
 *
 * Usage:
 *   1. Use this trait in your model
 *   2. Cast the field to an enum that implements HasDefinition
 *
 * The trait scans $casts for HasDefinition enums and dynamically
 * provides the label/severity values via getAttribute() and attributesToArray().
 * No need to add '{field}_label' / '{field}_severity' to $appends.
 */
trait HasEnumAccessors
{
    /**
     * Get the cached list of enum fields that implement HasDefinition.
     *
     * @return array<string, class-string<HasDefinition>>
     */
    protected function getDefinitionEnumFields(): array
    {
        static $cache = [];

        $class = static::class;

        if (isset($cache[$class])) {
            return $cache[$class];
        }

        $fields = [];

        foreach ($this->getCasts() as $field => $castType) {
            if (is_string($castType) && enum_exists($castType) && is_subclass_of($castType, HasDefinition::class)) {
                $fields[$field] = $castType;
            }
        }

        return $cache[$class] = $fields;
    }

    /**
     * Intercept attribute access for dynamic {field}_label and {field}_severity.
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        if (str_ends_with($key, '_label')) {
            $field = substr($key, 0, -6);
            if (isset($this->getDefinitionEnumFields()[$field])) {
                $enum = parent::getAttribute($field);

                return $enum instanceof HasDefinition ? $enum->label() : null;
            }
        }

        if (str_ends_with($key, '_severity')) {
            $field = substr($key, 0, -9);
            if (isset($this->getDefinitionEnumFields()[$field])) {
                $enum = parent::getAttribute($field);

                return $enum instanceof HasDefinition ? $enum->severity() : null;
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Include enum label/severity in array/JSON serialization automatically.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach ($this->getDefinitionEnumFields() as $field => $enumClass) {
            $enum = parent::getAttribute($field);
            $attributes["{$field}_label"] = $enum instanceof HasDefinition ? $enum->label() : null;
            $attributes["{$field}_severity"] = $enum instanceof HasDefinition ? $enum->severity() : null;
        }

        return $attributes;
    }
}
