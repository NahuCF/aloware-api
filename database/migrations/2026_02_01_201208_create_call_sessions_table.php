<?php

use App\Enums\CallSessionStatus;
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
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('call_sid')->index();
            $table->foreignId('line_id')->constrained()->onDelete('cascade');
            $table->string('from_number');
            $table->json('path')->default('[]');
            $table->json('context')->default('{}');
            $table->string('status')->default(CallSessionStatus::InProgress->value);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
