<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function test()
    {
        return response()->json(["test" => "hello world"]);
    }
    /**
     * 课程列表查询
     * GET /api/courses
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::query();

        // 按日期范围筛选
        if ($request->has('start_date')) {
            $query->where('scheduled_at', '>=', $request->input('start_date'));
        }
        if ($request->has('end_date')) {
            $query->where('scheduled_at', '<=', $request->input('end_date') . ' 23:59:59');
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
        if ($request->boolean('available_only')) {
            $query->having('confirmed_count', '<', DB::raw('capacity'));
        }

        // 排序
        $sortBy = $request->input('sort_by', 'scheduled_at');
        $sortOrder = $request->input('sort_order', 'asc');

        if ($sortBy === 'remaining_slots') {
            // 按剩余名额排序：capacity - confirmed_count
            $query->orderByRaw('(capacity - confirmed_count) ' . ($sortOrder === 'desc' ? 'DESC' : 'ASC'));
        } else {
            $allowedSorts = ['scheduled_at', 'name', 'capacity', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy('scheduled_at', 'asc');
            }
        }

        // 分页
        $perPage = min($request->input('per_page', 15), 100);
        $courses = $query->paginate($perPage);

        // 转换数据格式
        $courses->getCollection()->transform(function ($course) {
            $remainingSlots = max(0, $course->capacity - $course->confirmed_count);

            return [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description,
                'capacity' => $course->capacity,
                'scheduled_at' => $course->scheduled_at->toISOString(),
                'duration_minutes' => $course->duration_minutes,
                'status' => $course->status,
                'confirmed_count' => $course->confirmed_count,
                'waitlisted_count' => $course->waitlisted_count,
                'remaining_slots' => $remainingSlots,
                'is_full' => $remainingSlots === 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $courses->items(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }
}
