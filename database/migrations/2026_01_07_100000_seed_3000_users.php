<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 关键：指定中文
        $faker = Faker::create('zh_CN');
        $password = Hash::make('password');
        $now = now();

        $users = [];
        for ($i = 0; $i < 3000; $i++) {
            $users[] = [
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'email_verified_at' => $now,
                'password' => $password,
                'remember_token' => \Illuminate\Support\Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // 每 500 条批量插入一次，提高性能
            if (count($users) === 500) {
                DB::table('users')->insert($users);
                $users = [];
            }
        }

        // 插入剩余的记录
        if (!empty($users)) {
            DB::table('users')->insert($users);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 删除所有用户（谨慎使用）
        DB::table('users')->truncate();
    }
};
