<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeEnumCommand extends GeneratorCommand
{
    protected $name = 'make:enum';
    protected $description = 'Create a new enum class';
    protected $type = 'Enum';

    protected function getStub()
    {
        return base_path('stubs/enum.stub');
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Enums';
    }
}
