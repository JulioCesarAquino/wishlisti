<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_grants', function (Blueprint $table) {
            $table->id();
            $table->morphs('grantable');
            $table->string('feature');
            $table->string('source');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['grantable_type', 'grantable_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_grants');
    }
};
