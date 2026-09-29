<?php

namespace App\Actions\Manager;

use App\Models\StudentGrade;
use Illuminate\Support\Carbon;

class GetManagerAcademicTrendAction
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /**
     * Monthly average performance for one school, class, or subject
     * (whichever the frontend's dropdown selected), for the given year.
     * Returns both the raw percentage and a 0-5 "level" (matching the
     * V.Poor..Excellent scale the Graph component already plots).
     *
     * @param string $type one of: school | class | subject
     * @param int $id the institution/classroom/subject id selected
     */
    public function handle(array $institutionIds, string $type, int $id, ?int $year = null): array
    {
        $year = $year ?: Carbon::now()->year;

        $query = StudentGrade::query()
            ->where('type', 'years_marks')
            ->whereYear('date', $year)
            ->whereHas('student', fn ($q) => $q->whereIn('institution_id', $institutionIds));

        match ($type) {
            'school' => $query->whereHas('student', fn ($q) => $q->where('institution_id', $id)),
            'class' => $query->whereHas('student', fn ($q) => $q->where('classroom_id', $id)),
            'subject' => $query->where('subject_id', $id),
            default => null,
        };

        $rows = $query->selectRaw('MONTH(date) as month, SUM(score) as obtained, SUM(total) as total')
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $series = [];
        foreach (self::MONTHS as $index => $label) {
            $monthNumber = $index + 1;
            $row = $rows->get($monthNumber);
            $obtained = (float) ($row->obtained ?? 0);
            $total = (float) ($row->total ?? 0);
            $percentage = $total > 0 ? round(($obtained / $total) * 100, 2) : 0.0;

            $series[] = [
                'month' => $label,
                'percentage' => $percentage,
                'level' => round(min(5, max(0, $percentage / 20)), 1),
            ];
        }

        return [
            'type' => $type,
            'id' => $id,
            'year' => $year,
            'series' => $series,
        ];
    }
}
