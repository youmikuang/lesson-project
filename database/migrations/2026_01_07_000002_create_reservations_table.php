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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->default(0);
            $table->integer('course_id')->default(0);
            // 预约状态: confirmed=已确认, waitlisted=候补中, cancelled=已取消
            $table->enum('status', ['confirmed', 'waitlisted', 'cancelled'])->default('confirmed');
            // 候补位置，仅当 status=waitlisted 时有意义，用于确定递补顺序
            $table->unsignedInteger('waitlist_position')->nullable();
            // 预约时间
            $table->timestamp('reserved_at')->useCurrent();
            // 取消时间
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // 唯一约束：防止同一用户对同一课程创建多条有效预约记录
            // 注意：cancelled 状态的记录不参与此约束，需要在应用层处理
            // 这里使用复合唯一索引，配合应用层逻辑确保不重复预约，但是同一状态下只能有一个数据
            $table->unique(['user_id', 'course_id'], 'reservations_user_course_unique');

            // 索引：查询某课程的所有预约
            $table->index(['course_id', 'status']);
            // 索引：查询某用户的所有预约
            $table->index(['user_id', 'status']);
            // 索引：候补名单排序查询
            $table->index(['course_id', 'status', 'waitlist_position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
