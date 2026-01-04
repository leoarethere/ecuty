<?php

namespace App\Models;

use Filament\Panel;
use App\Models\Employee;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable; // ✅ TAMBAHKAN INI
use Filament\Models\Contracts\FilamentUser; // ✅ TAMBAHKAN INI
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable implements FilamentUser // ✅ IMPLEMENT INTERFACE
{
    use HasFactory, Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'signature_image_path',
        'unit_kerja_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    // ✅ TAMBAHKAN METHOD INI - SANGAT PENTING!
    public function canAccessPanel(Panel $panel): bool
    {
        // Mengizinkan semua user yang sudah login untuk mengakses panel 'admin'
        // Anda bisa menambahkan logika khusus jika diperlukan
        return true;
        
        // Atau jika ingin hanya role tertentu:
        // return in_array($this->role, ['admin', 'kepala_stasiun', 'tata_usaha', 'ketua_tim', 'pegawai']);
    }
    
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class);
    }
    
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->role === 'ketua_tim' && $user->unit_kerja_id) {
                UnitKerja::where('id', $user->unit_kerja_id)
                    ->update(['ketua_user_id' => $user->id]);
            }
            
            if ($user->employee) {
                $user->employee->unit_kerja_id = $user->unit_kerja_id;
                $user->employee->saveQuietly();
            }
        });
    }
}