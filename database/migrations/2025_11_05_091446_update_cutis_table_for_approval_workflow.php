<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cutis', function (Blueprint $table) {
            // 1. Tambah kolom form baru
            $table->string('jenis_cuti')->after('employee_id');
            $table->text('alamat_selama_cuti')->nullable()->after('alasan');

            // 2. Tambah kolom approval 3-tahap
            $table->enum('status_sdm', ['pending', 'approved', 'rejected'])->default('pending')->after('alamat_selama_cuti');
            $table->enum('status_tata_usaha', ['pending', 'approved', 'rejected'])->default('pending')->after('status_sdm');
            $table->enum('status_kepala', ['pending', 'approved', 'rejected'])->default('pending')->after('status_tata_usaha');
            
            // 3. Tambah kolom pelacak status global
            $table->string('status_global')->default('Menunggu Persetujuan SDM')->after('status_kepala');

            // 4. Hapus kolom status lama
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('cutis', function (Blueprint $table) {
            // Kebalikan dari 'up'
            $table->dropColumn([
                'jenis_cuti', 
                'alamat_selama_cuti', 
                'status_sdm', 
                'status_tata_usaha', 
                'status_kepala', 
                'status_global'
            ]);

            $table->string('status')->default('pending'); // Kembalikan kolom lama
        });
    }
};
