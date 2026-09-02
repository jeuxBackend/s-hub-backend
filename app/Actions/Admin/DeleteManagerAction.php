<?php

namespace App\Actions\Admin;

use App\Models\Admin;
use App\Models\Institution;
use App\Models\ManagerInvoice;
use Illuminate\Validation\ValidationException;

class DeleteManagerAction
{
    public function handle($id)
    {
        $manager = Admin::findOrFail($id);

        // institutions.manager_id and manager_invoices.manager_id both
        // cascade-delete on the DB side, so deleting a manager with either
        // still attached would silently wipe out every school they run
        // (and everything under those schools) plus their invoice history.
        if (Institution::where('manager_id', $manager->id)->exists()) {
            throw ValidationException::withMessages([
                'manager' => ['This manager still has institutions assigned to them. Reassign or delete those institutions before deleting this manager.'],
            ]);
        }

        if (ManagerInvoice::where('manager_id', $manager->id)->exists()) {
            throw ValidationException::withMessages([
                'manager' => ['This manager still has invoices on record. Resolve or remove those invoices before deleting this manager.'],
            ]);
        }

        $manager->delete();
        return true;
    }
}
