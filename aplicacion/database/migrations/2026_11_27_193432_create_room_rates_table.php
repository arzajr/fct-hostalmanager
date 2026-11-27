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
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained();
            $table->decimal('price_per_night', 10, 2);
            $table->date('valid_from');
            $table->date('valid_until');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};
