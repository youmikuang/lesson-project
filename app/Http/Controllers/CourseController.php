<?php

namespace App\Http\Controllers;

use App\Exceptions\CourseException;
use App\Http\Requests\Course\ListCoursesRequest;
use App\Http\Resources\CourseCollection;
use App\Services\CourseService;

/**
 * 课程控制器
 */
class CourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courseService
    )
    {
    }

    /**
     * 课程列表查询
     *
     * GET /api/courses
     *
     * 支持的查询参数：
     * - start_date: 开始日期筛选 (YYYY-MM-DD)
     * - end_date: 结束日期筛选 (YYYY-MM-DD)
     * - available_only: 只显示有空位的课程 (boolean)
     * - sort_by: 排序字段 (scheduled_at|name|capacity|remaining_slots|created_at)
     * - sort_order: 排序方向 (asc|desc)
     * - per_page: 每页数量 (1-100, 默认15)
     */
    public function index(ListCoursesRequest $request)
    {
        $data = $this->courseService->getAvailableCourses($request->filters());
        return $this->jsonResponse(0, 'OK', $data);
    }
}
