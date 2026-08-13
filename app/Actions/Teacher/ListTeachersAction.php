<?php

namespace App\Actions\Teacher;

use Illuminate\Http\Request;
use App\Enums\GenderType;
use App\Models\SchoolTimetableConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ListTeachersAction
{
    public function handle(Request $request)
    {
        $activeConfig = $this->activeConfig();

        $query = $this->baseQuery($request)
            ->when($activeConfig, function ($query) use ($activeConfig) {
                $query->with(['teacherAvailabilities' => function ($availabilityQuery) use ($activeConfig) {
                    $availabilityQuery
                        ->where('config_id', $activeConfig->id)
                        ->orderBy('weekday')
                        ->orderBy('start_time');
                }]);
            });

        return $query
            ->latest()
            ->paginate($request->get('per_page', 10))
            ->through(function ($user) use ($activeConfig) {
                $user->is_approved = $user->password !== null;
                $availabilities = $activeConfig && $user->relationLoaded('teacherAvailabilities')
                    ? $user->teacherAvailabilities
                    : collect();

                $user->setAttribute('timetable_availability', [
                    'config_id' => $activeConfig?->id,
                    'has_custom_availability' => $availabilities->isNotEmpty(),
                    'available_all_institute_time' => (bool) ($activeConfig && $availabilities->isEmpty()),
                    'default_institute_time' => $activeConfig && $availabilities->isEmpty()
                        ? [
                            'school_start_time' => $activeConfig->school_start_time,
                            'school_end_time' => $activeConfig->school_end_time,
                            'working_days' => $activeConfig->workingDays
                                ->sortBy('weekday')
                                ->map(fn ($day) => [
                                    'weekday' => (int) $day->weekday,
                                    'is_open' => (bool) $day->is_open,
                                ])
                                ->values()
                                ->all(),
                        ]
                        : null,
                    'availabilities' => $availabilities
                        ->map(fn ($availability) => [
                            'id' => $availability->id,
                            'config_id' => $availability->config_id,
                            'weekday' => (int) $availability->weekday,
                            'start_time' => $availability->start_time,
                            'end_time' => $availability->end_time,
                            'availability_type' => $availability->availability_type,
                        ])
                        ->values()
                        ->all(),
                ]);

                return $user;
            });
    }

    /**
     * Male/female totals for the same role-scoped, filtered set as handle() — ignores pagination.
     */
    public function countsByGender(Request $request): array
    {
        $counts = $this->baseQuery($request)
            ->toBase()
            ->reorder()
            ->select('gender', DB::raw('COUNT(*) as total'))
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return [
            'male' => (int) ($counts[GenderType::Male->value] ?? 0),
            'female' => (int) ($counts[GenderType::Female->value] ?? 0),
        ];
    }

    private function activeConfig(): ?SchoolTimetableConfig
    {
        $requester = auth()->user();

        return SchoolTimetableConfig::query()
            ->with('workingDays')
            ->where('institution_id', $requester->institution_id)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    private function baseQuery(Request $request)
    {
        $requester = auth()->user();

        return User::query()
            ->whereIn('role', ['teacher', 'school-admin'])
            ->where('institution_id', $requester->institution_id)
            ->when($request->filled('name'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('first_name', 'like', '%' . $request->name . '%')
                        ->orWhere('last_name', 'like', '%' . $request->name . '%')
                        ->orWhere('sur_name', 'like', '%' . $request->name . '%');
                });
            })
            ->when($request->filled('phone'), function ($q) use ($request) {
                $q->where('phone_number', 'like', '%' . $request->phone . '%');
            })
            ->when($request->filled('email'), function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->email . '%');
            });
    }
}
