<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which tabs of the public page are on, and in which order. Events without
 * rows (all of them, at first) show every tab in the default order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sections');
    }
};
