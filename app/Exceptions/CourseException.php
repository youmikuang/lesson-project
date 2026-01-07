<?php

namespace App\Exceptions;

/**
 * 课程相关异常
 */
class CourseException extends BusinessException
{
    /**
     * 课程不可预约异常
     */
    public static function notAvailable(): self
    {
        return new self(
            msg: '该课程当前不可预约',
            httpStatusCode: 400
        );
    }

    /**
     * 课程不存在异常
     */
    public static function notFound(): self
    {
        return new self(
            msg: '课程不存在',
            httpStatusCode: 404
        );
    }
}
