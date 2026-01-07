<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 课程列表查询请求验证
 */
class ListCoursesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'available_only' => ['nullable', 'in:0,1,true,false'],
            'sort_by' => ['nullable', 'string', 'in:scheduled_at,name,capacity,remaining_slots,created_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => '开始日期格式不正确',
            'start_date.date_format' => '开始日期格式应为 YYYY-MM-DD',
            'end_date.date' => '结束日期格式不正确',
            'end_date.date_format' => '结束日期格式应为 YYYY-MM-DD',
            'end_date.after_or_equal' => '结束日期不能早于开始日期',
            'sort_by.in' => '排序字段不支持，可选值：scheduled_at, name, capacity, remaining_slots, created_at',
            'sort_order.in' => '排序方向不正确，可选值：asc, desc',
            'per_page.integer' => '每页数量必须是整数',
            'per_page.min' => '每页数量最少为 1',
            'per_page.max' => '每页数量最多为 100',
            'page.integer' => '页码必须是整数',
            'page.min' => '页码最少为 1',
        ];
    }

    /**
     * 获取验证后的筛选参数
     */
    public function filters(): array
    {
        return [
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
            'available_only' => $this->boolean('available_only'),
            'sort_by' => $this->input('sort_by', 'scheduled_at'),
            'sort_order' => $this->input('sort_order', 'asc'),
            'per_page' => min($this->input('per_page', 15), 100),
        ];
    }
}
