<?php

namespace App\Actions\Assignment;

use App\Models\Assignment;
use App\Jobs\SendAssignmentNotificationJob;
use Illuminate\Support\Facades\DB;

class StoreAssignmentAction
{
    public function handle(array $data): Assignment
    {
        return DB::transaction(function () use ($data) {
            $assignmentData = [
                'title' => $data['title'],
                'assignment_text' => $data['assignment_text'] ?? null, // assignment_text (nullable)
                'classroom_id' => $data['class_id'], // clss_id mapped to classroom_id in db
                'subject_id' => $data['subject_id'],
                'teacher_id' => auth()->id(),
                'status' => $data['status'] ?? 'draft',
                'submission_end_date' => $data['submission_end_date'], // submission_end_date
                'assignment_date' => $data['assignment_date'], // assignment_date
            ];

            // Handle file upload if present
            if (isset($data['file']) && $data['file']) {
                $filename = time() . '_' . $data['file']->getClientOriginalName();
                $path = $data['file']->storeAs('assignments', $filename, 'public');

                $assignmentData['file_path'] = $path;
                $assignmentData['file_original_name'] = $data['file']->getClientOriginalName();
            }

            $assignment = Assignment::create($assignmentData);

            // Dispatch notification job if the assignment is assigned (not draft)
            if ($assignment->status === 'assigned') {
                SendAssignmentNotificationJob::dispatch($assignment);
            }

            return $assignment;
        });
    }
}