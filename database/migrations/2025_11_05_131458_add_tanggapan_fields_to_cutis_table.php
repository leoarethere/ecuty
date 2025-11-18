<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutis', function (Blueprint $table) {
            $table->text('tanggapan_sdm')->nullable()->after('status_sdm');
            $table->text('tanggapan_tata_usaha')->nullable()->after('status_tata_usaha');
            $table->text('tanggapan_kepala')->nullable()->after('status_kepala');
        });
    }

    public function down(): void
    {
        Schema::table('cutis', function (Blueprint $table) {
            $table->dropColumn(['tanggapan_sdm', 'tanggapan_tata_usaha', 'tanggapan_kepala']);
        });
    }
};
