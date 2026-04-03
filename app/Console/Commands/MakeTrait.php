<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeTrait extends Command
{
    protected $signature = 'make:trait {name}';
    protected $description = 'Create a new trait in app/Traits';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $path = app_path("Traits/{$name}.php");

        if (file_exists($path)) {
            $this->error("❌ Trait '{$name}' already exists.");
            return Command::FAILURE;
        }

        if (! is_dir(app_path('Traits'))) {
            mkdir(app_path('Traits'), 0755, true);
        }

        file_put_contents($path, <<<PHP
<?php

namespace App\Traits;

trait {$name}
{
    //
}
PHP);

        $this->info("✅ Trait '{$name}' created successfully at app/Traits/{$name}.php");
        return Command::SUCCESS;
    }
}
