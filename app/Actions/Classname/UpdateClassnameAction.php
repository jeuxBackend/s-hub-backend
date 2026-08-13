<?php

namespace App\Actions\Classname;

use App\Models\Classname;

class UpdateClassnameAction
{
    public function handle(Classname $classname, array $data): Classname
    {
        $classname->update($data);

        return $classname->refresh();
    }
}
