<?php

namespace App\Actions\Classname;

use App\Models\Classname;

class StoreClassnameAction
{
    public function handle(array $data): Classname
    {
        return Classname::create([
            'institution_id' => $data['institution_id'],
            'name' => $data['name'],
        ]);
    }
}
