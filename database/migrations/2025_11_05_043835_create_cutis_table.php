<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cutis', function (Blueprint $table) {
            $table->id();

            // INI ADALAH KUNCI RELASINYA
            $table->foreignId('employee_id') // Relasi ke tabel 'employees'
                  ->constrained('employees') // Nama tabelnya
                  ->cascadeOnDelete();       // Jika pegawai dihapus, cutinya ikut terhapus

            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->text('alasan');

            // Kolom ini PENTING untuk langkah "Persetujuan Cuti"
            $table->string('status')->default('pending'); // 'pending', 'approved', 'rejected'

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cutis');
    }
};