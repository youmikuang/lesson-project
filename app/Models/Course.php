<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'capacity',
        'scheduled_at',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'capacity' => 'integer',
            'duration_minutes' => 'integer',
            'status' => CourseStatus::class,
        ];
    }

    // ==================== 关联关系 ====================

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function confirmedReservations(): HasMany
    {
        return $this->reservations()->confirmed();
    }

    public function waitlistedReservations(): HasMany
    {
        return $this->reservations()->waitlisted()->orderByPosition();
    }

    // ==================== Query Scopes ====================

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CourseStatus::Open);
    }

    public function scopeScheduledBetween(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if ($startDate) {
            $query->where('scheduled_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('scheduled_at', '<=', $endDate . ' 23:59:59');
        }

        return $query;
    }

    public function scopeWithReservationCounts(Builder $query): Builder
    {
        return $query->withCount([
            'reservations as confirmed_count' => fn ($q) => $q->where('status', ReservationStatus::Confirmed),
            'reservations as waitlisted_count' => fn ($q) => $q->where('status', ReservationStatus::Waitlisted),
        ]);
    }

    public function scopeHasAvailableSlots(Builder $query): Builder
    {
        return $query->havingRaw('confirmed_count < capacity');
    }

    public function scopeOrderBySortField(Builder $query, string $sortBy, string $sortOrder = 'asc'): Builder
    {
        $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'remaining_slots') {
            return $query->orderByRaw("(capacity - confirmed_count) {$sortOrder}");
        }

        $allowedSorts = ['scheduled_at', 'name', 'capacity', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'scheduled_at';

        return $query->orderBy($sortBy, $sortOrder);
    }

    // ==================== 访问器 ====================

    public function getConfirmedCountAttribute(): int
    {
        return $this->confirmedReservations()->count();
    }

    public function getRemainingSlots(): int
    {
        return max(0, $this->capacity - $this->confirmed_count);
    }

    // ==================== 业务方法 ====================

    public function hasAvailableSlots(): bool
    {
        return $this->confirmed_count < $this->capacity;
    }

    public function isOpen(): bool
    {
        return $this->status === CourseStatus::Open;
    }
}
