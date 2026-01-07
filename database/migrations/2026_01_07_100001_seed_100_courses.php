<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $faker = Faker::create();
        $now = now();

        $courseNames = [
            'PHP 基础入门', 'Laravel 框架实战', 'Vue.js 前端开发', 'React 高级应用',
            'MySQL 数据库优化', 'Redis 缓存技术', 'Docker 容器化部署', 'Kubernetes 集群管理',
            'Python 数据分析', 'Java Spring Boot', 'Go 语言编程', 'Node.js 后端开发',
            'TypeScript 进阶', 'GraphQL API 设计', 'RESTful 接口规范', '微服务架构设计',
            'Linux 系统管理', 'Git 版本控制', 'CI/CD 持续集成', '云计算基础',
        ];

        $statuses = ['open', 'closed', 'cancelled', 'completed'];

        $courses = [];
        for ($i = 0; $i < 100; $i++) {
            $courses[] = [
                'name' => $courseNames[array_rand($courseNames)] . ' - ' . ($i + 1),
                'description' => $faker->paragraph(3),
                'capacity' => $faker->numberBetween(10, 50),
                'scheduled_at' => $faker->dateTimeBetween('-1 month', '+3 months')->format('Y-m-d H:i:s'),
                'duration_minutes' => $faker->randomElement([30, 45, 60, 90, 120]),
                'status' => $faker->randomElement($statuses),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('courses')->insert($courses);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('courses')->truncate();
    }
};
