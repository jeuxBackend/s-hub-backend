<?php

namespace App\Actions\Admin;

use App\Models\Admin;

class DeleteManagerAction
{
    public function handle($id)
    {
        $manager = Admin::findOrFail($id);
        $manager->delete();
        return true;
    }
}
