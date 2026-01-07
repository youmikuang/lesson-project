<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'status' => 'confirmed',
            'waitlist_position' => null,
            'reserved_at' => now(),
            'cancelled_at' => null,
        ];
    }

    /**
     * 已确认状态
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'waitlist_position' => null,
        ]);
    }

    /**
     * 候补状态
     */
    public function waitlisted(int $position = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'waitlisted',
            'waitlist_position' => $position,
        ]);
    }

    /**
     * 已取消状态
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'waitlist_position' => null,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * 指定用户
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * 指定课程
     */
    public function forCourse(Course $course): static
    {
        return $this->state(fn (array $attributes) => [
            'course_id' => $course->id,
        ]);
    }
}
