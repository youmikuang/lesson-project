<?php

namespace App\Http\Requests\Reservation;

use App\Exceptions\ReservationException;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 取消预约请求验证
 */
class DestroyReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Reservation $reservation */
        $reservation = $this->route('reservation');

        // 验证是否是用户自己的预约
        if ($reservation->user_id !== request()->get('user_id')) {
            throw ReservationException::unauthorized();
        }

        return true;
    }

    public function rules(): array
    {
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
