<?php

namespace ChrisReedIO\AIModelFactory\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use OpenAI\Laravel\Facades\OpenAI;

use Throwable;

/**
 * @mixin Model
 * @mixin HasJsonSchema
 */
trait AIModelFactory
{
    use HasJsonSchema;

    public static function getGenerationPrompt(array $input): View
    {
        return view('ai-model-factory::ai.prompts.generate_model', ['seed' => array_filter(self::getGenerationSeed($input))]);
    }

    public static function getGenerationSeed(?array $input = null): array
    {
        return $input ?? [];
    }

    public static function getGeneratableRelations(): array
    {
        return [];
    }

    /**
     * Generate and create a model instance with relations.
     *
     * @throws Throwable
     */
    public static function generate(?array $input = null): static
    {
        return static::generativeCreate($input);
    }

    /**
     * Generate data and return a new model instance without persisting it.
     *
     * @throws Throwable
     */
    public static function generativeMake(?array $input = null): static
    {
        $seedData = static::prepareSeedData($input);
        $responseData = static::callOpenAI($seedData);
        $modelData = static::prepareModelData($responseData, $seedData);
        $modelData = static::morphModelData($modelData);

        return static::make($modelData->all());
    }

    /**
     * Generate data, create, and persist a new model instance with relations.
     *
     * @throws Throwable
     */
    public static function generativeCreate(?array $input = null): static
    {
        $seedData = static::prepareSeedData($input);
        $responseData = static::callOpenAI($seedData);
        $modelData = static::prepareModelData($responseData, $seedData);
        $modelData = static::morphModelData($modelData);

        return static::persistModelWithRelations($modelData);
    }

    /**
     * Prepare seed data by merging input with the generation seed.
     */
    protected static function prepareSeedData(?array $input): array
    {
        return collect(static::getGenerationSeed($input))->merge($input)->all();
    }

    /**
     * Call OpenAI to generate data based on the seed data.
     */
    protected static function callOpenAI(array $seedData): array
    {
        // dd(static::getGenerationPrompt($seedData)->render());
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'temperature' => 1.0,
            'response_format' => static::getRootSchema(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => static::getGenerationPrompt($seedData)->render(),
                ],
            ],
        ]);

        $content = $response->choices[0]->message->content;

        return json_decode($content, true);
    }

    /**
     * Prepare model data by merging response data with seed data.
     */
    protected static function prepareModelData(array $responseData, array $seedData): Collection
    {
        return collect($responseData)->merge($seedData)->map(function ($value) {
            return ($value === '' || $value === '-') ? null : $value;
        });
    }

    /**
     * Morph the model data before creating the model instance. Designed to be overridden.
     */
    protected static function morphModelData(Collection $modelData): Collection
    {
        return $modelData;
    }

    /**
     * Persist the model instance and its relations in the database.
     *
     * @throws Throwable
     */
    protected static function persistModelWithRelations(Collection $modelData): static
    {
        DB::beginTransaction();

        try {
            $item = self::create($modelData->all());
            static::generateRelations($item, $modelData);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $item;
    }

    /**
     * Generate and persist relations for a model.
     */
    protected static function generateRelations(Model $item, Collection $modelData): void
    {
        $relations = static::getGeneratableRelations();

        foreach ($relations as $relationName => $description) {
            if (is_int($relationName)) {
                $relationName = $description;
                $description = null;
            }

            $related = $item->$relationName()->getRelated();
            $generatedItems = $modelData[$relationName] ?? [];

            if (! is_int(array_key_first($generatedItems))) {
                $generatedItems = [$generatedItems];
            }

            foreach ($generatedItems as $generatedItem) {
                $generatedItem = $related::morphModelData(collect($generatedItem))->all();
                $relatedItem = $related::make($generatedItem);
                $item->$relationName()->save($relatedItem);
            }
        }
    }
}
