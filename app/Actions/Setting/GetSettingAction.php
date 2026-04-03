<?php

namespace App\Actions\Setting;

use App\Models\Setting;
use App\Models\User;

class GetSettingAction
{
    public function handle(User $requester): ?Setting
    {
        
        // return Setting::query()
        //     ->when($requester->institution->id, function ($query, $institutionId) {
        //         $query->where('institution_id', $institutionId);
        //     }, function ($query) {
        //         $query->whereNull('institution_id');
        //     })
        //     ->first();
        return Setting::first();
    }
}
