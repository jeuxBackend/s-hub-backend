<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Enums\UserRole;
use App\Models\Admin;

class ListStudentsAction
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    // Only load relations actually needed — callers can extend if required
    private array $baseRelations = [
        'classroom',
        'guardian',
        'studentInvoices',
        'studentGrades',
        'attendanceRecords',
    ];

    public function handle(array $filters = [], array $relations = [])
    {
        $user = auth()->user();

        $query = Student::query()
            ->with(array_merge($this->baseRelations, $relations))
            ->latest();

        $this->applyRoleConstraints($query, $user, $filters);
        $this->applyFilters($query, $filters, $user);

        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);

        return $query->paginate($perPage);
    }

    // -------------------------------------------------------------------------
    // Role-based scoping
    // -------------------------------------------------------------------------

    private function applyRoleConstraints($query, $user, array $filters): void
    {
        // Admins: scoped externally via $filters (e.g. school_ids from ManagerController)
        if ($user instanceof Admin) {
            return;
        }

        match (true) {
            $user->isRole(UserRole::Teacher) => $this->scopeToTeacher($query, $user),
            $user->isRole(UserRole::Parent) => $this->scopeToParent($query, $user),

            // Principal & SchoolAdmin: same institution scope
            $user->isRole(UserRole::Principal),
            $user->isRole(UserRole::SchoolAdmin) => $this->scopeToInstitution($query, $user),

            default => null,
        };
    }

    private function scopeToTeacher($query, $user): void
    {
        $query->whereHas('classroom.teachers', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }

    private function scopeToParent($query, $user): void
    {
        $query->where('guardian_id', $user->id);
    }

    private function scopeToInstitution($query, $user): void
    {
        $query->where('institution_id', $user->institution_id);
    }

    // -------------------------------------------------------------------------
    // Filters — guarded so non-admins can't escape their scope
    // -------------------------------------------------------------------------

    private function applyFilters($query, array $filters, $user): void
    {
        $isAdmin = $user instanceof Admin;

        // Admin-only filters — prevent scope escalation by other roles
        if ($isAdmin) {
            if (!empty($filters['school_ids'])) {
                $query->whereIn('institution_id', $filters['school_ids']);
            }

            if (!empty($filters['institution_id'])) {
                $query->where('institution_id', $filters['institution_id']);
            }
        }

        // Shared filters — safe for all roles and combined with AND logic
        $query
            ->when(!empty($filters['class_id']), function ($q) use ($filters) {
                $q->where('classroom_id', $filters['class_id']);
            })
            ->when(!empty($filters['gender']), function ($q) use ($filters) {
                $q->where('gender', $filters['gender']);
            })
            ->when(!empty($filters['age_group']), function ($q) use ($filters) {
                $ageGroup = trim($filters['age_group']);

                if (str_contains($ageGroup, '+')) {
                    $minimumAge = (int) rtrim($ageGroup, '+');
                    $q->where('age', '>=', $minimumAge);

                    return;
                }

                [$minimumAge, $maximumAge] = array_map('intval', explode('-', $ageGroup));
                $q->whereBetween('age', [$minimumAge, $maximumAge]);
            })
            ->when(isset($filters['student_name']) && $filters['student_name'] !== '', function ($q) use ($filters) {
                $search = mb_strtolower(trim($filters['student_name']));

                $q->where(function ($nameQuery) use ($search) {
                    $nameQuery->whereRaw('LOWER(first_name) LIKE ?', ['%' . $search . '%'])
                        ->orWhereRaw('LOWER(sur_name) LIKE ?', ['%' . $search . '%']);
                });
            })
            ->when(!empty($filters['tuition_status']), function ($q) use ($filters) {
                $status = $filters['tuition_status'];

                $q->whereHas('studentInvoices', function ($invoiceQuery) use ($status) {
                    $invoiceQuery->whereIn('id', function ($sub) {
                        $sub->selectRaw('MAX(id)')
                            ->from('student_invoices')
                            ->groupBy('student_id');
                    })->where('status', $status);
                });
            });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolvePerPage(?int $requested): int
    {
        if (!$requested || $requested < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($requested, self::MAX_PER_PAGE);
    }
}
