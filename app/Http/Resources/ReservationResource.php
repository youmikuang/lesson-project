<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 预约资源格式化
 *
 * @property int $id
 * @property int $course_id
 * @property int $user_id
 * @property string $status
 * @property int|null $waitlist_position
 * @property \Carbon\Carbon|null $reserved_at
 * @property \Carbon\Carbon|null $cancelled_at
 * @property \App\Models\Course $course
 */
class ReservationResource extends JsonResource
{
    /**
     * 附加的响应消息
     */
    protected ?string $responseMessage = null;

    /**
     * 附加的数据
     */
    protected array $additionalData = [];

    /**
     * 设置响应消息
     */
    public function withMessage(string $message): self
    {
        $this->responseMessage = $message;

        return $this;
    }

    /**
     * 设置附加数据
     */
    public function withAdditionalData(array $data): self
    {
        $this->additionalData = $data;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $isWaitlisted = $this->status === 'waitlisted';
        $statusText = match ($this->status) {
            'confirmed' => '已确认预约',
            'waitlisted' => '候补名单中',
            'cancelled' => '已取消',
            default => $this->status,
        };

        $data = [
            'reservation_id' => $this->id,
            'course_id' => $this->course_id,
            'status' => $this->status,
            'status_text' => $statusText,
        ];

        // 预约成功时包含课程名称
        if ($this->relationLoaded('course') && $this->course) {
            $data['course_name'] = $this->course->name;
        }

        // 候补时包含位置信息
        if ($isWaitlisted && $this->waitlist_position !== null) {
            $data['is_waitlisted'] = true;
            $data['waitlist_position'] = $this->waitlist_position;
        } elseif ($this->status === 'confirmed') {
            $data['is_waitlisted'] = false;
        }

        // 已确认时包含剩余名额
        if (isset($this->additionalData['remaining_slots'])) {
            $data['remaining_slots'] = $this->additionalData['remaining_slots'];
        }

        // 取消时包含取消时间
        if ($this->cancelled_at) {
            $data['cancelled_at'] = $this->cancelled_at->toISOString();
        }

        // 合并附加数据
        return array_merge($data, $this->additionalData);
    }

    /**
     * 自定义响应包装
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => $this->responseMessage ?? '操作成功',
        ];
    }
}
