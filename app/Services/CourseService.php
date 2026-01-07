<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * 课程服务类
 *
 * 处理课程相关的业务逻辑
 */
class CourseService
{
    /**
     * 获取课程列表
     *
     * @param  array  $filters  筛选参数
     *                          - start_date: 开始日期
     *                          - end_date: 结束日期
     *                          - available_only: 只显示有空位的课程
     *                          - sort_by: 排序字段
     *                          - sort_order: 排序方向
     *                          - per_page: 每页数量
     */
    public function getAvailableCourses(array $filters)
    {
        $query = Course::query();

        // 按日期范围筛选
        if (! empty($filters['start_date'])) {
            $query->where('scheduled_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('scheduled_at', '<=', $filters['end_date'].' 23:59:59');
        }

        // 只显示开放状态的课程
        $query->where('status', 'open');

        // 子查询：计算每个课程的已确认预约数
        $query->withCount([
            'reservations as confirmed_count' => function ($q) {
                $q->where('status', 'confirmed');
            },
            'reservations as waitlisted_count' => function ($q) {
                $q->where('status', 'waitlisted');
            },
        ]);

        // 只显示还有空位的课程
        if (! empty($filters['available_only'])) {
            $query->having('confirmed_count', '<', DB::raw('capacity'));
        }

        // 排序
        $sortBy = $filters['sort_by'] ?? 'scheduled_at';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        if ($sortBy === 'remaining_slots') {
            // 按剩余名额排序：capacity - confirmed_count
            $query->orderByRaw('(capacity - confirmed_count) '.($sortOrder === 'desc' ? 'DESC' : 'ASC'));
        } else {
            $allowedSorts = ['scheduled_at', 'name', 'capacity', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy('scheduled_at', 'asc');
            }
        }
        $query = $query->limit($filters['per_page'] ?? 15);
        return [
            'total' => $query->count(),
            'data' => $query->get()->toArray(),
        ];
    }
}
