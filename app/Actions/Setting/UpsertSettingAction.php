<?php

namespace App\Actions\Setting;

use App\Models\Admin;
use App\Models\Setting;

class UpsertSettingAction
{
    /**
     * Create or update the single global settings row (about us, privacy
     * policy, terms & conditions). created_by is only ever stamped on first
     * creation — later edits don't overwrite who originally created it.
     */
    public function handle(Admin $admin, array $data): Setting
    {
        $setting = Setting::first();

        if ($setting) {
            $setting->update($data);
            return $setting->fresh();
        }

        $data['created_by'] = $admin->id;

        return Setting::create($data);
    }
}
