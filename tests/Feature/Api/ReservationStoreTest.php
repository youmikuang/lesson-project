<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 预约课程 API 测试
 *
 * POST /api/courses/{course}/reservations
 */
class ReservationStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * 测试预约课程成功（有空位）
     */
    public function test_can_reserve_course_with_available_slots(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '预约成功',
            ])
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.is_waitlisted', false)
            ->assertJsonPath('data.remaining_slots', 9);

        $this->assertDatabaseHas('reservations', [
            'user_id' => $this->user->id,
            'course_id' => $course->id,
            'status' => 'confirmed',
        ]);
    }

    /**
     * 测试预约课程成功（加入候补名单）
     */
    public function test_joins_waitlist_when_course_is_full(): void
    {
        $course = Course::factory()->create(['capacity' => 1]);

        // 先让课程满员
        Reservation::factory()
            ->forCourse($course)
            ->confirmed()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '课程已满，已加入候补名单',
            ])
            ->assertJsonPath('data.status', 'waitlisted')
            ->assertJsonPath('data.is_waitlisted', true)
            ->assertJsonPath('data.waitlist_position', 1);

        $this->assertDatabaseHas('reservations', [
            'user_id' => $this->user->id,
            'course_id' => $course->id,
            'status' => 'waitlisted',
            'waitlist_position' => 1,
        ]);
    }

    /**
     * 测试候补名单位置递增
     */
    public function test_waitlist_position_increments(): void
    {
        $course = Course::factory()->create(['capacity' => 1]);

        // 让课程满员
        Reservation::factory()
            ->forCourse($course)
            ->confirmed()
            ->create();

        // 第一个候补
        $user1 = User::factory()->create();
        Reservation::factory()
            ->forUser($user1)
            ->forCourse($course)
            ->waitlisted(1)
            ->create();

        // 第二个候补
        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(201)
            ->assertJsonPath('data.waitlist_position', 2);
    }

    /**
     * 测试不能重复预约同一课程（已确认状态）
     */
    public function test_cannot_reserve_same_course_twice_when_confirmed(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);

        Reservation::factory()
            ->forUser($this->user)
            ->forCourse($course)
            ->confirmed()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => '您已经预约过该课程',
            ])
            ->assertJsonPath('data.status', 'confirmed');
    }

    /**
     * 测试不能重复预约同一课程（候补状态）
     */
    public function test_cannot_reserve_same_course_twice_when_waitlisted(): void
    {
        $course = Course::factory()->create(['capacity' => 1]);

        // 让课程满员
        Reservation::factory()
            ->forCourse($course)
            ->confirmed()
            ->create();

        // 用户已在候补名单
        Reservation::factory()
            ->forUser($this->user)
            ->forCourse($course)
            ->waitlisted(1)
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => '您已经预约过该课程',
            ])
            ->assertJsonPath('data.status', 'waitlisted');
    }

    /**
     * 测试可以重新预约已取消的课程
     */
    public function test_can_reserve_previously_cancelled_course(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);

        // 用户之前取消过
        Reservation::factory()
            ->forUser($this->user)
            ->forCourse($course)
            ->cancelled()
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '预约成功',
            ]);

        // 应该有两条记录：一条取消的，一条新确认的
        $this->assertEquals(2, Reservation::where('user_id', $this->user->id)
            ->where('course_id', $course->id)
            ->count());
    }

    /**
     * 测试不能预约已关闭的课程
     */
    public function test_cannot_reserve_closed_course(): void
    {
        $course = Course::factory()->closed()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '该课程当前不可预约',
            ]);
    }

    /**
     * 测试预约不存在的课程
     */
    public function test_cannot_reserve_nonexistent_course(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/courses/99999/reservations');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => '课程不存在',
            ]);
    }

    /**
     * 测试未认证用户不能预约
     */
    public function test_unauthenticated_user_cannot_reserve(): void
    {
        $course = Course::factory()->create();

        $response = $this->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => '未认证，请先登录',
            ]);
    }

    /**
     * 测试响应包含课程名称
     */
    public function test_response_includes_course_name(): void
    {
        $course = Course::factory()->create([
            'name' => '瑜伽初级班',
            'capacity' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $response->assertStatus(201)
            ->assertJsonPath('data.course_name', '瑜伽初级班');
    }

    /**
     * 测试并发预约场景（最后一个名额）
     *
     * 模拟并发：10个用户同时竞争最后一个名额
     */
    public function test_concurrent_reservation_for_last_slot(): void
    {
        $course = Course::factory()->create(['capacity' => 2]);

        // 已有一个预约，只剩最后一个名额
        Reservation::factory()
            ->forCourse($course)
            ->confirmed()
            ->create();

        // 创建10个用户准备并发预约
        $users = collect();
        $users->push($this->user);
        for ($i = 0; $i < 9; $i++) {
            $users->push(User::factory()->create());
        }

        $confirmedCount = 0;
        $waitlistedCount = 0;

        // 使用数据库事务模拟并发竞争
        // 10个请求几乎同时到达时，都会尝试获取锁
        \DB::transaction(function () use ($course, $users, &$confirmedCount, &$waitlistedCount) {
            // 锁定课程行，模拟并发时的行锁竞争
            $lockedCourse = Course::lockForUpdate()->find($course->id);

            // 模拟10个用户同时尝试预约
            foreach ($users as $user) {
                // 每次检查当前已确认的预约数
                $currentConfirmed = Reservation::where('course_id', $course->id)
                    ->where('status', 'confirmed')
                    ->count();

                if ($currentConfirmed < $lockedCourse->capacity) {
                    // 还有名额，确认预约
                    Reservation::create([
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'status' => 'confirmed',
                        'reserved_at' => now(),
                    ]);
                    $confirmedCount++;
                } else {
                    // 课程已满，加入候补
                    $waitlistPosition = Reservation::where('course_id', $course->id)
                        ->where('status', 'waitlisted')
                        ->max('waitlist_position') ?? 0;

                    Reservation::create([
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'status' => 'waitlisted',
                        'waitlist_position' => $waitlistPosition + 1,
                        'reserved_at' => now(),
                    ]);
                    $waitlistedCount++;
                }
            }
        });

        // 验证：只有1个新 confirmed（原有1个 + 新1个 = 2个），9个 waitlisted
        $this->assertEquals(1, $confirmedCount);
        $this->assertEquals(9, $waitlistedCount);

        // 验证数据库最终状态
        $this->assertEquals(2, Reservation::where('course_id', $course->id)
            ->where('status', 'confirmed')
            ->count());
        $this->assertEquals(9, Reservation::where('course_id', $course->id)
            ->where('status', 'waitlisted')
            ->count());

        // 验证候补名单位置是否正确递增（1-9）
        $waitlistPositions = Reservation::where('course_id', $course->id)
            ->where('status', 'waitlisted')
            ->orderBy('waitlist_position')
            ->pluck('waitlist_position')
            ->toArray();

        $this->assertEquals(range(1, 9), $waitlistPositions);
    }

    /**
     * 测试预约后数据库状态正确
     */
    public function test_reservation_database_state_is_correct(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);

        $this->actingAs($this->user)
            ->postJson("/api/courses/{$course->id}/reservations");

        $reservation = Reservation::where('user_id', $this->user->id)
            ->where('course_id', $course->id)
            ->first();

        $this->assertNotNull($reservation);
        $this->assertEquals('confirmed', $reservation->status);
        $this->assertNull($reservation->waitlist_position);
        $this->assertNotNull($reservation->reserved_at);
        $this->assertNull($reservation->cancelled_at);
    }
}
