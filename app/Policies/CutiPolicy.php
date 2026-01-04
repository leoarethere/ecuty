<?php

namespace App\Policies;

use App\Models\Cuti;
use App\Models\User;

class CutiPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Semua user bisa lihat list
    }

    public function view(User $user, Cuti $cuti): bool
    {
        // Admin bisa lihat semua
        if ($user->role === 'admin') return true;
        
        // Pegawai hanya bisa lihat miliknya sendiri
        if ($user->role === 'pegawai') {
            return $cuti->employee->user_id === $user->id;
        }
        
        // Ketua tim bisa lihat cuti di unitnya
        if ($user->role === 'ketua_tim') {
            return $cuti->employee->unitKerja && 
                   $cuti->employee->unitKerja->ketua_user_id === $user->id;
        }
        
        // Tata usaha dan kepala bisa lihat semua
        return in_array($user->role, ['tata_usaha', 'kepala_stasiun']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'pegawai']);
    }

    public function update(User $user, Cuti $cuti): bool
    {
        // Admin selalu bisa edit
        if ($user->role === 'admin') return true;
        
        // Pegawai hanya bisa edit miliknya yang masih pending
        if ($user->role === 'pegawai') {
            return $cuti->employee->user_id === $user->id &&
                   $cuti->status_global === 'Menunggu Persetujuan Atasan Langsung';
        }
        
        return false;
    }

    public function delete(User $user, Cuti $cuti): bool
    {
        // Admin selalu bisa hapus
        if ($user->role === 'admin') return true;
        
        // Pegawai hanya bisa hapus miliknya yang masih pending
        if ($user->role === 'pegawai') {
            return $cuti->employee->user_id === $user->id &&
                   $cuti->status_global === 'Menunggu Persetujuan Atasan Langsung';
        }
        
        return false;
    }
}