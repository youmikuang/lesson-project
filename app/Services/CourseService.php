<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CourseService
{
    public function getAvailableCourses(array $filters): LengthAwarePaginator
    {
        return Course::query()
            ->open()
            ->scheduledBetween(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            )
            ->withReservationCounts()
            ->when(
                $filters['available_only'] ?? false,
                fn ($query) => $query->hasAvailableSlots()
            )
            ->orderBySortField(
                $filters['sort_by'] ?? 'scheduled_at',
                $filters['sort_order'] ?? 'asc'
            )
            ->paginate(perPage:$filters['per_page'] ?? 15,  page: $filters['page'] ?? 1 );
    }
}
