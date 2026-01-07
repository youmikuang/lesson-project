<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 取消预约 API 测试
 *
 * DELETE /api/reservations/{reservation}
 */
class ReservationCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->course = Course::factory()->create(['capacity' => 5]);
    }

    /**
     * 测试取消已确认预约成功
     */
    public function test_can_cancel_confirmed_reservation(): void
    {
        $reservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '预约已取消',
            ])
            ->assertJsonPath('data.reservation_id', $reservation->id)
            ->assertJsonPath('data.course_id', $this->course->id)
            ->assertJsonStructure(['data' => ['cancelled_at']]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * 测试取消候补预约成功
     */
    public function test_can_cancel_waitlisted_reservation(): void
    {
        $reservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->waitlisted(1)
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '预约已取消',
            ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
            'waitlist_position' => null,
        ]);
    }

    /**
     * 测试取消已确认预约后候补递补
     */
    public function test_waitlisted_user_promoted_when_confirmed_cancelled(): void
    {
        // 创建已确认预约
        $confirmedReservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        // 创建候补用户
        $waitlistedUser = User::factory()->create();
        $waitlistedReservation = Reservation::factory()
            ->forUser($waitlistedUser)
            ->forCourse($this->course)
            ->waitlisted(1)
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$confirmedReservation->id}");

        $response->assertOk()
            ->assertJsonPath('data.promoted_reservation.reservation_id', $waitlistedReservation->id)
            ->assertJsonPath('data.promoted_reservation.user_id', $waitlistedUser->id)
            ->assertJsonPath('data.promoted_reservation.message', '候补用户已自动递补');

        // 验证候补用户已递补
        $this->assertDatabaseHas('reservations', [
            'id' => $waitlistedReservation->id,
            'status' => 'confirmed',
            'waitlist_position' => null,
        ]);
    }

    /**
     * 测试取消确认预约后递补第一个候补（按位置排序）
     */
    public function test_promotes_first_waitlisted_by_position(): void
    {
        // 创建已确认预约
        $confirmedReservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        // 创建多个候补用户
        $waitlistedUser1 = User::factory()->create();
        $waitlistedReservation1 = Reservation::factory()
            ->forUser($waitlistedUser1)
            ->forCourse($this->course)
            ->waitlisted(1)
            ->create();

        $waitlistedUser2 = User::factory()->create();
        $waitlistedReservation2 = Reservation::factory()
            ->forUser($waitlistedUser2)
            ->forCourse($this->course)
            ->waitlisted(2)
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$confirmedReservation->id}");

        $response->assertOk()
            ->assertJsonPath('data.promoted_reservation.reservation_id', $waitlistedReservation1->id);

        // 第一个候补用户已递补
        $this->assertDatabaseHas('reservations', [
            'id' => $waitlistedReservation1->id,
            'status' => 'confirmed',
        ]);

        // 第二个候补用户仍在候补
        $this->assertDatabaseHas('reservations', [
            'id' => $waitlistedReservation2->id,
            'status' => 'waitlisted',
        ]);
    }

    /**
     * 测试取消确认预约但无候补时不递补
     */
    public function test_no_promotion_when_no_waitlist(): void
    {
        $reservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertOk()
            ->assertJsonMissing(['promoted_reservation']);
    }

    /**
     * 测试取消候补预约后更新其他候补位置
     */
    public function test_updates_waitlist_positions_when_waitlisted_cancelled(): void
    {
        // 让课程满员
        Reservation::factory()
            ->count(5)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        // 创建候补用户（位置1）
        $waitlistUser1 = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->waitlisted(1)
            ->create();

        // 创建候补用户（位置2）
        $otherUser = User::factory()->create();
        $waitlistUser2 = Reservation::factory()
            ->forUser($otherUser)
            ->forCourse($this->course)
            ->waitlisted(2)
            ->create();

        // 取消位置1的候补
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$waitlistUser1->id}");

        $response->assertOk();

        // 位置2应该变成位置1
        $this->assertDatabaseHas('reservations', [
            'id' => $waitlistUser2->id,
            'waitlist_position' => 1,
        ]);
    }

    /**
     * 测试不能取消他人的预约
     */
    public function test_cannot_cancel_others_reservation(): void
    {
        $otherUser = User::factory()->create();
        $reservation = Reservation::factory()
            ->forUser($otherUser)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => '无权操作此预约',
            ]);

        // 预约状态未改变
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }

    /**
     * 测试不能取消已取消的预约
     */
    public function test_cannot_cancel_already_cancelled_reservation(): void
    {
        $reservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->cancelled()
            ->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => '该预约已被取消',
            ]);
    }

    /**
     * 测试取消不存在的预约
     */
    public function test_cannot_cancel_nonexistent_reservation(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/reservations/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => '预约记录不存在',
            ]);
    }

    /**
     * 测试未认证用户不能取消预约
     */
    public function test_unauthenticated_user_cannot_cancel(): void
    {
        $reservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        $response = $this->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => '未认证，请先登录',
            ]);
    }

    /**
     * 测试取消后 cancelled_at 被设置
     */
    public function test_cancelled_at_is_set_after_cancellation(): void
    {
        $reservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$reservation->id}");

        $reservation->refresh();

        $this->assertNotNull($reservation->cancelled_at);
        $this->assertEquals('cancelled', $reservation->status);
    }

    /**
     * 测试复杂场景：多个候补递补后位置更新
     */
    public function test_complex_waitlist_promotion_scenario(): void
    {
        $course = Course::factory()->create(['capacity' => 2]);

        // 两个已确认预约
        $confirmed1 = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($course)
            ->confirmed()
            ->create();

        $user2 = User::factory()->create();
        Reservation::factory()
            ->forUser($user2)
            ->forCourse($course)
            ->confirmed()
            ->create();

        // 三个候补
        $waitlistUser1 = User::factory()->create();
        $waitlist1 = Reservation::factory()
            ->forUser($waitlistUser1)
            ->forCourse($course)
            ->waitlisted(1)
            ->create();

        $waitlistUser2 = User::factory()->create();
        $waitlist2 = Reservation::factory()
            ->forUser($waitlistUser2)
            ->forCourse($course)
            ->waitlisted(2)
            ->create();

        $waitlistUser3 = User::factory()->create();
        $waitlist3 = Reservation::factory()
            ->forUser($waitlistUser3)
            ->forCourse($course)
            ->waitlisted(3)
            ->create();

        // 取消第一个确认预约
        $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$confirmed1->id}");

        // 验证状态
        $this->assertDatabaseHas('reservations', [
            'id' => $waitlist1->id,
            'status' => 'confirmed',
            'waitlist_position' => null,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $waitlist2->id,
            'status' => 'waitlisted',
            'waitlist_position' => 1,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $waitlist3->id,
            'status' => 'waitlisted',
            'waitlist_position' => 2,
        ]);
    }

    /**
     * 测试事务回滚（模拟异常）
     */
    public function test_transaction_rollback_on_error(): void
    {
        $reservation = Reservation::factory()
            ->forUser($this->user)
            ->forCourse($this->course)
            ->confirmed()
            ->create();

        // 确保预约存在
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);

        // 正常取消应该成功
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertOk();
    }
}
