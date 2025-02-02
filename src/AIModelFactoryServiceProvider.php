<?php

namespace ChrisReedIO\AIModelFactory;

use ChrisReedIO\AIModelFactory\Commands\AIModelFactoryCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AIModelFactoryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-ai-model-factory')
            ->hasConfigFile()
            ->hasViews('ai-model-factory')
            ->hasMigration('create_laravel_ai_model_factory_table')
            ->hasCommand(AIModelFactoryCommand::class);
    }
}
