<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('skills')->insert([
            ['name' => 'sales', 'description' => 'Sales', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'support', 'description' => 'Support', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('skills')->whereIn('name', ['sales', 'support'])->delete();
    }
};
