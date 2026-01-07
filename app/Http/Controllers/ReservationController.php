<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    /**
     * 预约课程
     * POST /api/courses/{course}/reservations
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        $userId = $request->user()->id;

        // 使用数据库事务确保数据一致性
        return DB::transaction(function () use ($course, $userId) {
            // 锁定课程记录，防止并发问题
            $course = Course::lockForUpdate()->find($course->id);

            // 检查课程状态
            if ($course->status !== 'open') {
                return response()->json([
                    'success' => false,
                    'message' => '该课程当前不可预约',
                ], 400);
            }

            // 检查用户是否已经预约过这个课程（非取消状态）
            $existingReservation = Reservation::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->whereIn('status', ['confirmed', 'waitlisted'])
                ->first();

            if ($existingReservation) {
                $statusText = $existingReservation->isConfirmed() ? '已确认预约' : '候补名单中';
                return response()->json([
                    'success' => false,
                    'message' => '您已经预约过该课程',
                    'data' => [
                        'reservation_id' => $existingReservation->id,
                        'status' => $existingReservation->status,
                        'status_text' => $statusText,
                    ],
                ], 409);
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

                return response()->json([
                    'success' => true,
                    'message' => '预约成功',
                    'data' => [
                        'reservation_id' => $reservation->id,
                        'course_id' => $course->id,
                        'course_name' => $course->name,
                        'status' => 'confirmed',
                        'status_text' => '已确认预约',
                        'is_waitlisted' => false,
                        'remaining_slots' => $course->capacity - $confirmedCount - 1,
                    ],
                ], 201);
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

            return response()->json([
                'success' => true,
                'message' => '课程已满，已加入候补名单',
                'data' => [
                    'reservation_id' => $reservation->id,
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'status' => 'waitlisted',
                    'status_text' => '候补名单中',
                    'is_waitlisted' => true,
                    'waitlist_position' => $reservation->waitlist_position,
                ],
            ], 201);
        });
    }

    /**
     * 取消预约
     * DELETE /api/reservations/{reservation}
     */
    public function destroy(Request $request, Reservation $reservation): JsonResponse
    {
        $userId = $request->user()->id;

        // 验证是否是用户自己的预约
        if ($reservation->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => '无权操作此预约',
            ], 403);
        }

        // 检查预约状态
        if ($reservation->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => '该预约已被取消',
            ], 400);
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
                // 查找候补名单中最早的一条记录
                $firstWaitlisted = Reservation::where('course_id', $courseId)
                    ->where('status', 'waitlisted')
                    ->orderBy('waitlist_position')
                    ->lockForUpdate()
                    ->first();

                if ($firstWaitlisted) {
                    // 将候补转为确认
                    $firstWaitlisted->update([
                        'status' => 'confirmed',
                        'waitlist_position' => null,
                    ]);

                    $promotedReservation = $firstWaitlisted;

                    Log::info('候补用户已递补', [
                        'reservation_id' => $firstWaitlisted->id,
                        'user_id' => $firstWaitlisted->user_id,
                        'course_id' => $courseId,
                        'previous_position' => $firstWaitlisted->waitlist_position,
                    ]);
                }
            } else {
                // 如果取消的是候补预约，需要更新其他候补的位置
                Reservation::where('course_id', $courseId)
                    ->where('status', 'waitlisted')
                    ->where('waitlist_position', '>', $originalPosition)
                    ->decrement('waitlist_position');
            }

            $response = [
                'success' => true,
                'message' => '预约已取消',
                'data' => [
                    'reservation_id' => $reservation->id,
                    'course_id' => $courseId,
                    'cancelled_at' => $reservation->cancelled_at->toISOString(),
                ],
            ];

            if ($promotedReservation) {
                $response['data']['promoted_reservation'] = [
                    'reservation_id' => $promotedReservation->id,
                    'user_id' => $promotedReservation->user_id,
                    'message' => '候补用户已自动递补',
                ];
            }

            return response()->json($response);
        });
    }
}
