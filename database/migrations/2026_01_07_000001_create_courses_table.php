<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->enum('status', ['open', 'closed', 'cancelled', 'completed'])->default('open');
            $table->timestamps();

            // 索引：按时间查询课程
            $table->index('scheduled_at');
            // 索引：按状态筛选课程
            $table->index('status');
            // 复合索引：常见查询场景 - 查找开放的未来课程
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
