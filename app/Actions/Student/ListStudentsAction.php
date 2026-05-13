<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Enums\UserRole;
use App\Models\Admin;

class ListStudentsAction
{
    private const DEFAULT_PER_PAGE = 10;
    private const MAX_PER_PAGE = 100;

    // Only load relations actually needed — callers can extend if required
    private array $baseRelations = [
        'classroom',
        'guardian',
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

        // Shared filters — safe for all roles
        if (!empty($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        if (isset($filters['name']) && $filters['name'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['name'] . '%')
                    ->orWhere('sur_name', 'like', '%' . $filters['name'] . '%');
            });
        }

        if (isset($filters['email']) && $filters['email'] !== '') {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }

        if (isset($filters['phone_number']) && $filters['phone_number'] !== '') {
            $query->where('phone_number', 'like', '%' . $filters['phone_number'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['term'])) {
            $query->where('term', $filters['term']);
        }
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