<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class UpdateManagerAction
{
    public function handle(array $data, $id)
    {
        $manager = Admin::findOrFail($id);
        
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        
        $manager->update($data);
        return $manager;
    }
}
