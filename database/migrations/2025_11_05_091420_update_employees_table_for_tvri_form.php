<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 1. Tambah kolom baru
            $table->string('unit_kerja')->nullable()->after('jabatan');
            $table->date('tanggal_bergabung')->nullable()->after('unit_kerja');
            $table->string('telp')->nullable()->after('email');
            $table->text('alamat_domisili')->nullable()->after('telp');

            // 2. Rename kolom lama
            $table->renameColumn('sisa_cuti', 'sisa_cuti_tahunan');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Kebalikan dari 'up'
            $table->dropColumn(['unit_kerja', 'tanggal_bergabung', 'telp', 'alamat_domisili']);
            $table->renameColumn('sisa_cuti_tahunan', 'sisa_cuti');
        });
    }
};
