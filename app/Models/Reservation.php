<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'waitlist_position',
        'reserved_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'waitlist_position' => 'integer',
            'status' => ReservationStatus::class,
        ];
    }

    // ==================== 关联关系 ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // ==================== Query Scopes ====================

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Confirmed);
    }

    public function scopeWaitlisted(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Waitlisted);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Cancelled);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Confirmed,
            ReservationStatus::Waitlisted,
        ]);
    }

    public function scopeForCourse(Builder $query, int $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOrderByPosition(Builder $query): Builder
    {
        return $query->orderBy('waitlist_position');
    }

    // ==================== 业务方法 ====================

    public function isConfirmed(): bool
    {
        return $this->status === ReservationStatus::Confirmed;
    }

    public function isWaitlisted(): bool
    {
        return $this->status === ReservationStatus::Waitlisted;
    }

    public function isCancelled(): bool
    {
        return $this->status === ReservationStatus::Cancelled;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function cancel(): void
    {
        $this->update([
            'status' => ReservationStatus::Cancelled,
            'waitlist_position' => null,
            'cancelled_at' => now(),
        ]);
    }

    public function promote(): void
    {
        $this->update([
            'status' => ReservationStatus::Confirmed,
            'waitlist_position' => null,
        ]);
    }
}
