<?php

namespace App\Models;

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
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function confirmedReservations(): HasMany
    {
        return $this->reservations()->where('status', 'confirmed');
    }

    public function waitlistedReservations(): HasMany
    {
        return $this->reservations()->where('status', 'waitlisted')->orderBy('waitlist_position');
    }

    public function getConfirmedCountAttribute(): int
    {
        return $this->confirmedReservations()->count();
    }

    public function hasAvailableSlots(): bool
    {
        return $this->confirmed_count < $this->capacity;
    }
}
