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
        Schema::table('employees', function (Blueprint $table) {
            // 1. Buat kolom 'user_id'
            $table->foreignId('user_id') 
                  ->nullable()           // Boleh kosong (PENTING!)
                  ->after('id')          // Posisikan setelah kolom 'id'
                  ->constrained('users') // Terhubung ke tabel 'users'
                  ->nullOnDelete();     // Jika user dihapus, set kolom ini jadi NULL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id'); // Hapus relasi & kolom
        });
    }
};