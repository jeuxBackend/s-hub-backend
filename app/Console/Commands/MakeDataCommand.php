<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeDataCommand extends GeneratorCommand
{
    protected $name = 'make:data';
    protected $description = 'Create a new data class';
    protected $type = 'Data';

    protected function getStub()
    {
        return base_path('stubs/data.stub');
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Data';
    }
}
