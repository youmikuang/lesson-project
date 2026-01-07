<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'capacity' => fake()->numberBetween(5, 30),
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90, 120]),
            'status' => 'open',
        ];
    }

    /**
     * 课程已满（容量为1，便于测试）
     */
    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => 1,
        ]);
    }

    /**
     * 课程已关闭
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    /**
     * 指定容量
     */
    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }

    /**
     * 指定日期
     */
    public function scheduledAt(\DateTimeInterface|string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $date,
        ]);
    }
}
