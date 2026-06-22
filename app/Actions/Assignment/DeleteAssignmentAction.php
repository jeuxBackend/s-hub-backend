<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;
use Illuminate\Support\Facades\DB;

class DeleteAssignmentAction
{
    public function handle(Assignment $assignment): bool
    {
        return DB::transaction(function () use ($assignment) {
            // Delete associated files if they exist
            if ($assignment->file_path) {
                \Storage::disk('public')->delete($assignment->file_path);
            }
            
            // Delete all submissions for this assignment
            $assignment->submissions()->delete();
            
            // Finally delete the assignment
            return $assignment->delete();
        });
    }
}