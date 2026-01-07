<?php

namespace App\Enums;

enum CourseStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Open => '开放',
            self::Closed => '已关闭',
            self::Cancelled => '已取消',
            self::Completed => '已完成',
        };
    }
}
