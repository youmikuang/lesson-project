<?php

namespace App\Services;

use App\Exceptions\CourseException;
use App\Exceptions\ReservationException;
use App\Models\Course;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 预约服务类
 *
 * 处理预约相关的业务逻辑，包括创建预约、取消预约、候补递补等
 */
class ReservationService
{
    /**
     * 创建预约
     *
     * @param  Course  $course  课程
     * @param  int  $userId  用户ID
     * @return array 包含预约信息和额外数据
     *
     * @throws CourseException 课程不可预约
     * @throws ReservationException 重复预约
     */
    public function createReservation(Course $course, int $userId): array
    {
        return DB::transaction(function () use ($course, $userId) {
            // 锁定课程记录，防止并发问题
            $course = Course::lockForUpdate()->find($course->id);

            // 检查课程状态
            if ($course->status !== 'open') {
                throw CourseException::notAvailable();
            }

            // 检查用户是否已经预约过这个课程（非取消状态）
            $existingReservation = Reservation::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->whereIn('status', ['confirmed', 'waitlisted'])
                ->first();

            if ($existingReservation) {
                throw ReservationException::alreadyReserved(
                    $existingReservation->id,
                    $existingReservation->status
                );
            }

            // 获取当前已确认的预约数量
            $confirmedCount = Reservation::where('course_id', $course->id)
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->count();

            // 判断是否还有空位
            if ($confirmedCount < $course->capacity) {
                // 有空位，直接预约成功
                $reservation = Reservation::create([
                    'user_id' => $userId,
                    'course_id' => $course->id,
                    'status' => 'confirmed',
                    'waitlist_position' => null,
                    'reserved_at' => now(),
                ]);

                $reservation->load('course');

                Log::info('预约成功', [
                    'reservation_id' => $reservation->id,
                    'user_id' => $userId,
                    'course_id' => $course->id,
                    'status' => 'confirmed',
                ]);

                return [
                    'reservation' => $reservation,
                    'remaining_slots' => $course->capacity - $confirmedCount - 1,
                    'message' => '预约成功',
                ];
            }

            // 课程已满，加入候补名单
            $maxWaitlistPosition = Reservation::where('course_id', $course->id)
                ->where('status', 'waitlisted')
                ->max('waitlist_position') ?? 0;

            $reservation = Reservation::create([
                'user_id' => $userId,
                'course_id' => $course->id,
                'status' => 'waitlisted',
                'waitlist_position' => $maxWaitlistPosition + 1,
                'reserved_at' => now(),
            ]);

            $reservation->load('course');

            Log::info('加入候补名单', [
                'reservation_id' => $reservation->id,
                'user_id' => $userId,
                'course_id' => $course->id,
                'waitlist_position' => $reservation->waitlist_position,
            ]);

            return [
                'reservation' => $reservation,
                'message' => '课程已满，已加入候补名单',
            ];
        });
    }

    /**
     * 取消预约
     *
     * @param  Reservation  $reservation  预约记录
     * @return array 包含取消结果和候补递补信息
     *
     * @throws ReservationException 预约已取消
     */
    public function cancelReservation(Reservation $reservation): array
    {
        // 检查预约状态
        if ($reservation->isCancelled()) {
            throw ReservationException::alreadyCancelled();
        }

        return DB::transaction(function () use ($reservation) {
            $wasConfirmed = $reservation->isConfirmed();
            $courseId = $reservation->course_id;
            $originalPosition = $reservation->waitlist_position;

            // 锁定相关记录
            $reservation = Reservation::lockForUpdate()->find($reservation->id);

            // 取消预约
            $reservation->update([
                'status' => 'cancelled',
                'waitlist_position' => null,
                'cancelled_at' => now(),
            ]);

            Log::info('预约已取消', [
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'course_id' => $courseId,
                'was_confirmed' => $wasConfirmed,
            ]);

            $promotedReservation = null;

            // 如果取消的是已确认预约，需要处理候补递补
            if ($wasConfirmed) {
                $promotedReservation = $this->promoteFirstWaitlisted($courseId);
            } else {
                // 如果取消的是候补预约，需要更新其他候补的位置
                $this->updateWaitlistPositions($courseId, $originalPosition);
            }

            return [
                'reservation' => $reservation,
                'promoted_reservation' => $promotedReservation,
            ];
        });
    }

    /**
     * 将候补名单第一位递补为确认状态
     *
     * @param  int  $courseId  课程ID
     * @return Reservation|null 递补的预约记录
     */
    private function promoteFirstWaitlisted(int $courseId): ?Reservation
    {
        // 查找候补名单中最早的一条记录
        $firstWaitlisted = Reservation::where('course_id', $courseId)
            ->where('status', 'waitlisted')
            ->orderBy('waitlist_position')
            ->lockForUpdate()
            ->first();

        if (! $firstWaitlisted) {
            return null;
        }

        $previousPosition = $firstWaitlisted->waitlist_position;

        // 将候补转为确认
        $firstWaitlisted->update([
            'status' => 'confirmed',
            'waitlist_position' => null,
        ]);

        Log::info('候补用户已递补', [
            'reservation_id' => $firstWaitlisted->id,
            'user_id' => $firstWaitlisted->user_id,
            'course_id' => $courseId,
            'previous_position' => $previousPosition,
        ]);

        // 更新剩余候补用户的位置
        $this->updateWaitlistPositions($courseId, $previousPosition);

        return $firstWaitlisted;
    }

    /**
     * 更新候补名单位置
     *
     * @param  int  $courseId  课程ID
     * @param  int|null  $removedPosition  被移除的位置
     */
    private function updateWaitlistPositions(int $courseId, ?int $removedPosition): void
    {
        if ($removedPosition === null) {
            return;
        }

        Reservation::where('course_id', $courseId)
            ->where('status', 'waitlisted')
            ->where('waitlist_position', '>', $removedPosition)
            ->decrement('waitlist_position');
    }
}
