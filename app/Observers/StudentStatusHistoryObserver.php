<?php

namespace App\Observers;

use App\Models\StatusHistory;
use App\Models\Student;

class StudentStatusHistoryObserver
{
    public function created(Student $student): void
    {
        StatusHistory::create([
            'statusable_type' => Student::class,
            'statusable_id' => $student->id,
            'status' => (bool) $student->status,
            'changed_at' => $student->created_at ?? now(),
        ]);
    }

    public function updated(Student $student): void
    {
        if (!$student->wasChanged('status')) {
            return;
        }

        StatusHistory::create([
            'statusable_type' => Student::class,
            'statusable_id' => $student->id,
            'status' => (bool) $student->status,
            'changed_at' => now(),
        ]);
    }
}
