<?php

namespace App\Actions\Classname;

use App\Models\Classname;

class DeleteClassnameAction
{
    public function handle(Classname $classname): void
    {
        $classname->delete();
    }
}
