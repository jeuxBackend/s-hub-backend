<?php

namespace App\Actions\Admin;

use App\Models\Institution;
use App\Models\User;

class GetSchoolsAction
{
    public function handle($data)
    {
        $schools = Institution::with('manager:id,first_name,sure_name,email')->with('category')->get();
        return $schools;
    }
}