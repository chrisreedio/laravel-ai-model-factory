<?php

namespace ChrisReedIO\AIModelFactory\Traits;

use ChrisReedIO\AIModelFactory\Contracts\AIFactoryInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;

use InvalidArgumentException;

use ReflectionEnum;

use function class_implements;
use function class_uses;
use function in_array;
use function is_int;

trait HasJsonSchema
{
    /**
     * @throws Exception
     */
    public static function generateJsonSchema(): array
    {
        /* @var class-string $model */
        $modelClass = static::class;
        /* @var Model|AIFactoryInterface $model */
        $model = new static();

        // If the model does not implement the AIFactoryInterface, throw an exception
        if (! in_array(AIFactoryInterface::class, class_implements($model))) {
            throw new \Exception('Model must implement AIFactoryInterface');
        }

        // Initialize the schema
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => false,
        ];

        $descriptions = $model::getFieldDescriptions();

        // Access the fillable attributes and casts directly
        $fillable = $model->fillable;
        $casts = $model->casts();

        // Loop through the descriptions and ensure they are in fillable.
        // Use their descriptions as the schema descriptions.
        // If they are not in fillable, throw an exception.
        // If the description key is in casts, handle the enum and array special cases
        // If it's an array, ensure it's marked as such in the schema
        // If it's an enum, ensure the enum values are added to the schema
        foreach ($descriptions as $attribute => $description) {
            if (! in_array($attribute, $fillable)) {
                throw new \Exception("Field '$attribute' is not fillable");
            }

            // Add the attribute to the schema with its description
            $schema['properties'][$attribute] = [
                'type' => 'string',
                'description' => $description,
            ];

            // Mark the attribute as required in the schema
            $schema['required'][] = $attribute;

            // Do we have a cast for this attribute?
            if (array_key_exists($attribute, $casts)) {
                // Get the cast for the attribute
                $cast = $casts[$attribute];
                if ($cast === 'array') {
                    // If the cast is an array, mark it as such in the schema
                    $schema['properties'][$attribute]['type'] = 'array';
                    // Build the 'items' schema for the array
                    // Only supports arrays of strings for now
                    // TODO: Add support for arrays of objects
                    $schema['properties'][$attribute]['items'] = [
                        'type' => 'string',
                    ];
                } elseif (self::isEnum($cast)) {
                    // If the cast is an enum, add the enum values to the schema
                    $cases = collect($cast::cases());
                    if ($cases->count() <= 10) {
                        $schema['properties'][$attribute]['enum'] = $cases->map(fn ($case) => $case->value)->toArray();
                        $schema['properties'][$attribute]['type'] = (new ReflectionEnum($cast))->getBackingType()->getName() === 'int' ? 'integer' : 'string';
                    }
                } elseif ($cast === 'integer') {
                    // If the cast is an integer, mark it as such in the schema
                    $schema['properties'][$attribute]['type'] = 'integer';
                }
            }
        }

        // Get Relations
        $relations = static::getGeneratableRelations();
        // dd($relations);
        // dd(static::getGenerationPrompt()->name());
        // Loop through the relations and find the class name of the relation
        foreach ($relations as $relationName => $description) {
            // dump($relation);
            if (is_int($relationName)) {
                $relationName = $description;
                $description = null;
            }
            // dump('Scanning relation: '.$relationName);
            // dd('Description: '.($description ?? 'No description'));
            $related = (new static)->$relationName()->getRelated();
            // Check if the relation is a singular or plural relation (HasOne, HasMany, etc.)
            // $relationType = (new static)->$relationName()->getRelationType();
            // dd($relationType);
            // dd($related);
            // Check that the $related model implements the HasJsonSchema trait
            if (in_array(HasJsonSchema::class, class_uses($related))) {
                // Generate the JSON schema for the related model
                if (self::isSingleRelation($relationName)) {
                    $relatedSchema = $related::generateJsonSchema();
                } else {
                    $relatedSchema = [
                        'type' => 'array',
                        'items' => $related::generateJsonSchema(),
                    ];
                }
                // $relatedSchema = $related::generateJsonSchema();
                // dump('Related Schema for '.$relationName);
                // dd($relatedSchema);
                // Add the related model's JSON schema to the response format
                $schema['properties'][$relationName] = $relatedSchema;
                $schema['required'][] = $relationName;
            }
        }

        return $schema;
    }

    /**
     * Check if a class is an enum.
     *
     * @param string $className
     * @return bool
     */
    private static function isEnum(string $className): bool
    {
        if (! class_exists($className)) {
            return false; // The class does not exist
        }

        return is_subclass_of($className, \UnitEnum::class);
    }

    /**
     * Determine if a relationship returns a single model or a collection.
     *
     * @param string $relationName The name of the relationship.
     * @return bool True if the relationship returns a single model, false if it returns a collection.
     */
    public static function isSingleRelation(string $relationName): bool
    {
        $instance = new static;

        if (! method_exists($instance, $relationName)) {
            throw new InvalidArgumentException("The relationship '{$relationName}' does not exist on the model.");
        }

        $relation = $instance->$relationName();

        return $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo
            || $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne
            || $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne
            || $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOneThrough;
    }

    public static function getRootSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => static::getResponseName(),
                'schema' => static::generateJsonSchema(),
                'strict' => true,
            ]
        ];
    }

    public static function getResponseName(): string
    {
        return strtolower(class_basename(static::class)).'_response';
    }
}

