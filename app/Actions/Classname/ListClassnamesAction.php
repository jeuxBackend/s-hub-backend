<?php

namespace App\Actions\Classname;

use App\Models\Classname;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListClassnamesAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $query = Classname::query()->orderBy('name');

        if (!empty($filters['institution_id'])) {
            $query->where('institution_id', $filters['institution_id']);
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        return $query->paginate(20);
    }
}
