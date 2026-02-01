<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('languages')->insert([
            ['code' => 'en', 'name' => 'English', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'es', 'name' => 'Spanish', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('languages')->whereIn('code', ['en', 'es'])->delete();
    }
};
