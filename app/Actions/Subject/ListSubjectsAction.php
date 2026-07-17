<?php

namespace App\Actions\Subject;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection; // ✅ Correct type
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class ListSubjectsAction
{
   public function handle(User $requester, array $filters = []): LengthAwarePaginator
    {
        $institutionId = $requester->institution->id;


        $query = Subject::query()
            ->where('institution_id', $institutionId)
            ->with(['classSubjectRequirement.teacher', 'classroom']);

        if (!empty($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }
// dd($query->get());
       return $query->paginate(10);

    }
}
