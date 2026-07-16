<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Mi Emisora');
            $table->string('slogan')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_live')->default(false);
            $table->foreignId('current_playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_settings');
    }
};
