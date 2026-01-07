<?php

namespace App\Exceptions;

/**
 * 预约相关异常
 */
class ReservationException extends BusinessException
{
    /**
     * 重复预约异常
     */
    public static function alreadyReserved(int $reservationId, string $status): self
    {
        $statusText = $status === 'confirmed' ? '已确认预约' : '候补名单中';

        return new self(
            msg: '您已经预约过该课程',
            httpStatusCode: 409,
            data: []
        );
    }

    /**
     * 无权操作异常
     */
    public static function unauthorized(): self
    {
        return new self(
            msg: '无权操作此预约',
            httpStatusCode: 403
        );
    }

    /**
     * 预约已取消异常
     */
    public static function alreadyCancelled(): self
    {
        return new self(
            msg: '该预约已被取消',
            httpStatusCode: 400
        );
    }

    /**
     * 预约不存在异常
     */
    public static function notFound(): self
    {
        return new self(
            msg: '预约记录不存在',
            httpStatusCode: 404
        );
    }
}
