<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 课程资源格式化
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $capacity
 * @property \Carbon\Carbon $scheduled_at
 * @property int $duration_minutes
 * @property string $status
 * @property int $confirmed_count
 * @property int $waitlisted_count
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $confirmedCount = $this->confirmed_count ?? $this->confirmedReservations()->count();
        $waitlistedCount = $this->waitlisted_count ?? $this->waitlistedReservations()->count();
        $remainingSlots = max(0, $this->capacity - $confirmedCount);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'scheduled_at' => $this->scheduled_at->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'confirmed_count' => $confirmedCount,
            'waitlisted_count' => $waitlistedCount,
            'remaining_slots' => $remainingSlots,
            'is_full' => $remainingSlots === 0,
        ];
    }
}
