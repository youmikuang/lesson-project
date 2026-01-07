<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 课程列表查询 API 测试
 *
 * GET /api/courses
 */
class CourseListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试获取课程列表成功
     */
    public function test_can_get_course_list(): void
    {
        Course::factory()->count(3)->create();

        $response = $this->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'capacity',
                        'scheduled_at',
                        'duration_minutes',
                        'status',
                        'confirmed_count',
                        'waitlisted_count',
                        'remaining_slots',
                        'is_full',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson(['success' => true]);
    }

    /**
     * 测试空课程列表
     */
    public function test_returns_empty_list_when_no_courses(): void
    {
        $response = $this->getJson('/api/courses');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
                'meta' => [
                    'total' => 0,
                ],
            ]);
    }

    /**
     * 测试只显示开放状态的课程
     */
    public function test_only_shows_open_courses(): void
    {
        Course::factory()->create(['status' => 'open', 'name' => 'Open Course']);
        Course::factory()->create(['status' => 'closed', 'name' => 'Closed Course']);
        Course::factory()->create(['status' => 'cancelled', 'name' => 'Cancelled Course']);

        $response = $this->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Open Course']);
    }

    /**
     * 测试按开始日期筛选
     */
    public function test_can_filter_by_start_date(): void
    {
        Course::factory()->create([
            'name' => 'Past Course',
            'scheduled_at' => '2024-01-01 10:00:00',
        ]);
        Course::factory()->create([
            'name' => 'Future Course',
            'scheduled_at' => '2025-06-01 10:00:00',
        ]);

        $response = $this->getJson('/api/courses?start_date=2025-01-01');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Future Course']);
    }

    /**
     * 测试按结束日期筛选
     */
    public function test_can_filter_by_end_date(): void
    {
        Course::factory()->create([
            'name' => 'Early Course',
            'scheduled_at' => '2025-01-15 10:00:00',
        ]);
        Course::factory()->create([
            'name' => 'Late Course',
            'scheduled_at' => '2025-12-01 10:00:00',
        ]);

        $response = $this->getJson('/api/courses?end_date=2025-06-30');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Early Course']);
    }

    /**
     * 测试按日期范围筛选
     */
    public function test_can_filter_by_date_range(): void
    {
        Course::factory()->create([
            'name' => 'Before Range',
            'scheduled_at' => '2025-01-01 10:00:00',
        ]);
        Course::factory()->create([
            'name' => 'In Range',
            'scheduled_at' => '2025-03-15 10:00:00',
        ]);
        Course::factory()->create([
            'name' => 'After Range',
            'scheduled_at' => '2025-06-01 10:00:00',
        ]);

        $response = $this->getJson('/api/courses?start_date=2025-03-01&end_date=2025-03-31');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'In Range']);
    }

    /**
     * 测试只显示有空位的课程
     */
    public function test_can_filter_available_only(): void
    {
        $availableCourse = Course::factory()->create([
            'name' => 'Available Course',
            'capacity' => 10,
        ]);

        $fullCourse = Course::factory()->create([
            'name' => 'Full Course',
            'capacity' => 1,
        ]);

        // 让 fullCourse 满员
        Reservation::factory()
            ->forCourse($fullCourse)
            ->confirmed()
            ->create();

        $response = $this->getJson('/api/courses?available_only=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Available Course']);
    }

    /**
     * 测试按开始时间排序（默认升序）
     */
    public function test_can_sort_by_scheduled_at_asc(): void
    {
        Course::factory()->create([
            'name' => 'Later Course',
            'scheduled_at' => '2025-06-01 10:00:00',
        ]);
        Course::factory()->create([
            'name' => 'Earlier Course',
            'scheduled_at' => '2025-03-01 10:00:00',
        ]);

        $response = $this->getJson('/api/courses?sort_by=scheduled_at&sort_order=asc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Earlier Course', $data[0]['name']);
        $this->assertEquals('Later Course', $data[1]['name']);
    }

    /**
     * 测试按开始时间降序排序
     */
    public function test_can_sort_by_scheduled_at_desc(): void
    {
        Course::factory()->create([
            'name' => 'Earlier Course',
            'scheduled_at' => '2025-03-01 10:00:00',
        ]);
        Course::factory()->create([
            'name' => 'Later Course',
            'scheduled_at' => '2025-06-01 10:00:00',
        ]);

        $response = $this->getJson('/api/courses?sort_by=scheduled_at&sort_order=desc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Later Course', $data[0]['name']);
        $this->assertEquals('Earlier Course', $data[1]['name']);
    }

    /**
     * 测试按剩余名额排序
     */
    public function test_can_sort_by_remaining_slots(): void
    {
        $courseWithMoreSlots = Course::factory()->create([
            'name' => 'More Slots',
            'capacity' => 20,
        ]);

        $courseWithFewerSlots = Course::factory()->create([
            'name' => 'Fewer Slots',
            'capacity' => 5,
        ]);

        // 给 courseWithMoreSlots 添加一些预约，使剩余名额为 15
        Reservation::factory()
            ->count(5)
            ->forCourse($courseWithMoreSlots)
            ->confirmed()
            ->create();

        $response = $this->getJson('/api/courses?sort_by=remaining_slots&sort_order=desc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('More Slots', $data[0]['name']);
        $this->assertEquals(15, $data[0]['remaining_slots']);
    }

    /**
     * 测试分页功能
     */
    public function test_pagination_works(): void
    {
        Course::factory()->count(25)->create();

        $response = $this->getJson('/api/courses?per_page=10&page=2');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);
    }

    /**
     * 测试每页数量限制（最大100）
     */
    public function test_per_page_max_limit(): void
    {
        Course::factory()->count(5)->create();

        $response = $this->getJson('/api/courses?per_page=200');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    /**
     * 测试课程数据包含预约统计
     */
    public function test_course_includes_reservation_counts(): void
    {
        $course = Course::factory()->create(['capacity' => 10]);

        // 3个确认预约
        Reservation::factory()
            ->count(3)
            ->forCourse($course)
            ->confirmed()
            ->create();

        // 2个候补预约
        Reservation::factory()
            ->forCourse($course)
            ->waitlisted(1)
            ->create();
        Reservation::factory()
            ->forCourse($course)
            ->waitlisted(2)
            ->create();

        $response = $this->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonPath('data.0.confirmed_count', 3)
            ->assertJsonPath('data.0.waitlisted_count', 2)
            ->assertJsonPath('data.0.remaining_slots', 7)
            ->assertJsonPath('data.0.is_full', false);
    }

    /**
     * 测试课程已满时的显示
     */
    public function test_shows_is_full_when_course_is_full(): void
    {
        $course = Course::factory()->create(['capacity' => 2]);

        Reservation::factory()
            ->count(2)
            ->forCourse($course)
            ->confirmed()
            ->create();

        $response = $this->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonPath('data.0.remaining_slots', 0)
            ->assertJsonPath('data.0.is_full', true);
    }

    /**
     * 测试无效的日期格式验证
     */
    public function test_validates_invalid_date_format(): void
    {
        $response = $this->getJson('/api/courses?start_date=invalid-date');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['start_date']]);
    }

    /**
     * 测试结束日期不能早于开始日期
     */
    public function test_validates_end_date_after_start_date(): void
    {
        $response = $this->getJson('/api/courses?start_date=2025-06-01&end_date=2025-01-01');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['end_date']]);
    }

    /**
     * 测试无效的排序字段验证
     */
    public function test_validates_invalid_sort_field(): void
    {
        $response = $this->getJson('/api/courses?sort_by=invalid_field');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * 测试无效的排序方向验证
     */
    public function test_validates_invalid_sort_order(): void
    {
        $response = $this->getJson('/api/courses?sort_order=invalid');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
