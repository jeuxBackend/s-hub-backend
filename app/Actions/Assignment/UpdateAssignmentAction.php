<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;
use App\Jobs\SendAssignmentNotificationJob;
use Illuminate\Support\Facades\DB;

class UpdateAssignmentAction
{
    public function handle(Assignment $assignment, array $data): Assignment
    {
        return DB::transaction(function () use ($assignment, $data) {
            $oldStatus = $assignment->status;

            $assignmentData = [
                'title' => $data['title'] ?? $assignment->title,
                'assignment_text' => $data['assignment_text'] ?? $assignment->assignment_text,
                'classroom_id' => $data['class_id'] ?? $assignment->classroom_id,
                'subject_id' => $data['subject_id'] ?? $assignment->subject_id,
                'status' => $data['status'] ?? $assignment->status,
                'submission_end_date' => $data['submission_end_date'] ?? $assignment->submission_end_date,
                'assignment_date' => $data['assignment_date'] ?? $assignment->assignment_date,
            ];

            // Handle file upload if present
            if (isset($data['file']) && $data['file']) {
                // Delete old file if exists
                if ($assignment->file_path) {
                    \Storage::disk('public')->delete($assignment->file_path);
                }
                
                $filename = time() . '_' . $data['file']->getClientOriginalName();
                $path = $data['file']->storeAs('assignments', $filename, 'public');

                $assignmentData['file_path'] = $path;
                $assignmentData['file_original_name'] = $data['file']->getClientOriginalName();
            }

            $assignment->update($assignmentData);

            // Dispatch notification job if status changed from draft to assigned
            if ($oldStatus !== 'assigned' && $assignment->status === 'assigned') {
                SendAssignmentNotificationJob::dispatch($assignment);
            }

            return $assignment->fresh();
        });
    }
}