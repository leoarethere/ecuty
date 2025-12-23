<?php

namespace App\Filament\Resources\CutiResource\Pages;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Employee;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\CutiResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCuti extends CreateRecord
{
    protected static string $resource = CutiResource::class;

    /**
     * Method ini dipanggil SEBELUM form di-render
     * Untuk mengisi default value employee_id jika form dibuka
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = Auth::user();
        
        // Jika bukan admin, coba cari dan isi employee_id otomatis
        if ($user->role !== 'admin') {
            $employee = Employee::where('user_id', $user->id)->first();
            
            if ($employee) {
                $data['employee_id'] = $employee->id;
            }
        }
        
        return $data;
    }

    /**
     * Method ini dipanggil SEBELUM data disimpan ke database
     * Ini adalah langkah terakhir sebelum INSERT untuk memanipulasi data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        
        // 1. Logika pengisian ID Pegawai (Wajib untuk non-admin)
        if ($user->role === 'pegawai') {
            $hasBackdate = collect($data['tanggal_cuti_array'])
                ->some(fn($date) => Carbon::parse($date)->lt(now()->startOfDay()));
            
            if ($hasBackdate) {
                Notification::make()
                    ->title('Backdate Tidak Diizinkan')
                    ->body('Pegawai tidak bisa mengajukan cuti backdate. Hubungi admin.')
                    ->danger()
                    ->persistent()
                    ->send();
                
                $this->halt();
            }
        }

        // ✅ 2. UPDATE: Auto-fill tanggal_mulai dan tanggal_akhir dari array
        if (!empty($data['tanggal_cuti_array']) && is_array($data['tanggal_cuti_array'])) {
            $dates = collect($data['tanggal_cuti_array'])->sort()->values();
            $lamaCuti = $dates->count(); // Hitung jumlah hari
            
        // === TAMBAHAN VALIDASI SALDO CUTI ===
        if ($data['jenis_cuti'] === 'Cuti Tahunan') {
            // Ambil data pegawai terbaru
            $employee = Employee::find($data['employee_id']);
            
            if ($employee->sisa_cuti_tahunan < $lamaCuti) {
                Notification::make()
                    ->title('Sisa Cuti Tidak Cukup')
                    ->body("Anda mengajukan {$lamaCuti} hari, namun sisa cuti Anda hanya {$employee->sisa_cuti_tahunan} hari.")
                    ->danger()
                    ->persistent()
                    ->send();
                
                $this->halt(); // Batalkan penyimpanan
            }
        }
            
        } else {
            // Validasi: Harus ada tanggal yang dipilih
            Notification::make()
                ->title('Error Validasi')
                ->body('Anda harus memilih minimal 1 tanggal untuk cuti.')
                ->danger()
                ->persistent()
                ->send();
            
            $this->halt();
        }

        // 3. Set Status (Sesuai Struktur Baru Unit Kerja)
        $data['status_atasan_langsung'] = 'pending'; 
        $data['status_tata_usaha'] = 'pending';
        $data['status_kepala'] = 'pending';
        
        // 4. Set Status Global Awal
        $data['status_global'] = 'Menunggu Persetujuan Atasan Langsung';

        // 5. Validasi lampiran untuk Cuti Sakit
        if ($data['jenis_cuti'] === 'Cuti Sakit' && empty($data['lampiran_link'])) {
            Notification::make()
                ->title('Error Validasi')
                ->body('Lampiran surat dokter wajib dilampirkan untuk Cuti Sakit.')
                ->danger()
                ->persistent()
                ->send();
            
            $this->halt();
        }

        return $data;
    }

    /**
     * Method alternatif: Hook setelah form divalidasi
     * Sebagai pengaman tambahan sebelum create
     */
    protected function afterValidate(): void
    {
        $user = Auth::user();
        
        // Double-check: Pastikan employee_id bisa ditemukan untuk non-admin
        if ($user->role !== 'admin') {
            $employee = Employee::where('user_id', $user->id)->first();
            
            if (!$employee) {
                Notification::make()
                    ->title('Akun Tidak Terhubung')
                    ->body('Silakan hubungi administrator untuk menautkan akun Anda dengan data pegawai.')
                    ->danger()
                    ->persistent()
                    ->send();
                    
                $this->halt();
            }
        }
    }

    protected function afterCreate(): void
    {
        $cuti = $this->record;
        
        // Cari Ketua Unit dari Pegawai yang mengajukan
        $unitKerja = $cuti->employee->unitKerja;
        
        if ($unitKerja && $unitKerja->ketua_user_id) {
            $ketuaTim = User::find($unitKerja->ketua_user_id);

            if ($ketuaTim) {
                // ✅ UPDATE: Tampilkan jumlah hari dari array
                $lamaCuti = $cuti->lama_cuti;
                
                Notification::make()
                    ->title('Pengajuan Cuti Baru')
                    ->body("Pegawai {$cuti->employee->nama} mengajukan cuti selama {$lamaCuti} hari. Mohon diperiksa.")
                    ->icon('heroicon-o-document-text')
                    ->warning()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('Lihat')
                            ->url(CutiResource::getUrl('index')),
                    ])
                    ->sendToDatabase($ketuaTim);
            }
        }
    }
}