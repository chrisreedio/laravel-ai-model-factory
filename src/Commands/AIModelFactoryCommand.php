<?php

namespace ChrisReedIO\AIModelFactory\Commands;

use Illuminate\Console\Command;

class AIModelFactoryCommand extends Command
{
    public $signature = 'laravel-ai-model-factory';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
