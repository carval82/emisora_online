<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('station_settings', function (Blueprint $table) {
            $table->string('live_host_name')->nullable()->after('is_live');
            $table->timestamp('live_started_at')->nullable()->after('live_host_name');
        });
    }

    public function down(): void
    {
        Schema::table('station_settings', function (Blueprint $table) {
            $table->dropColumn(['live_host_name', 'live_started_at']);
        });
    }
};
