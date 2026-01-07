<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reservation\DestroyReservationRequest;
use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Course;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

/**
 * 预约控制器
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService
    ) {}

    /**
     * 预约课程
     *
     * POST /api/courses/{course}/reservations
     *
     * 业务逻辑：
     * - 有空位时直接确认预约
     * - 无空位时加入候补名单
     * - 使用事务保证数据一致性
     */
    public function store(StoreReservationRequest $request, Course $course): JsonResponse
    {
        $result = $this->reservationService->createReservation(
            $course,
            $request->getUserId()
        );

        $resource = (new ReservationResource($result['reservation']))
            ->withMessage($result['message']);

        // 添加剩余名额信息（仅确认预约时）
        if (isset($result['remaining_slots'])) {
            $resource->withAdditionalData([
                'remaining_slots' => $result['remaining_slots'],
            ]);
        }

        return $resource->response()->setStatusCode(201);
    }

    /**
     * 取消预约
     *
     * DELETE /api/reservations/{reservation}
     *
     * 业务逻辑：
     * - 验证用户权限（在 FormRequest 中处理）
     * - 如果取消的是已确认预约，自动递补候补名单第一位
     * - 使用事务保证数据一致性
     */
    public function destroy(DestroyReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $result = $this->reservationService->cancelReservation($reservation);

        $data = [
            'reservation_id' => $result['reservation']->id,
            'course_id' => $result['reservation']->course_id,
            'cancelled_at' => $result['reservation']->cancelled_at->toISOString(),
        ];

        // 如果有候补递补，添加递补信息
        if ($result['promoted_reservation']) {
            $data['promoted_reservation'] = [
                'reservation_id' => $result['promoted_reservation']->id,
                'user_id' => $result['promoted_reservation']->user_id,
                'message' => '候补用户已自动递补',
            ];
        }

        return response()->json([
            'success' => true,
            'message' => '预约已取消',
            'data' => $data,
        ]);
    }
}
