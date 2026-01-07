<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * 课程列表分页资源
 */
class CourseCollection extends ResourceCollection
{
    /**
     * 指定资源类
     */
    public $collects = CourseResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
            ],
        ];
    }
}
