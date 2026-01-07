<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 创建预约请求验证
 */
class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // course_id 从路由获取，无需额外参数验证
        return [];
    }

    /**
     * 获取当前用户ID
     */
    public function getUserId(): int
    {
        return $this->user()->id;
    }
}
