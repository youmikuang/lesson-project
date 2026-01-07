<?php

namespace App\Models;

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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isWaitlisted(): bool
    {
        return $this->status === 'waitlisted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
