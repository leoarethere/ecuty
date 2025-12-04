<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\Cuti;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Employee;
use Filament\Forms\Form;
use App\Models\UnitKerja;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Services\FormCutiGenerator;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use App\Notifications\CutiStatusUpdated;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Resources\CutiResource\Pages;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CutiResource extends Resource
{
    protected static ?string $model = Cuti::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $modelLabel = 'Pengajuan Cuti';
    protected static ?string $pluralModelLabel = 'Data Pengajuan Cuti';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label('Nama Pegawai')
                    ->relationship('employee', 'nama')
                    ->searchable()
                    ->required(fn () => Auth::user()->role === 'admin')
                    ->visible(fn () => Auth::user()->role === 'admin'),

                Select::make('jenis_cuti')
                    ->label('Jenis Cuti yang Diambil')
                    ->options([
                        'Cuti Tahunan' => 'Cuti Tahunan',
                        'Cuti Besar' => 'Cuti Besar',
                        'Cuti Sakit' => 'Cuti Sakit',
                        'Cuti Melahirkan' => 'Cuti Melahirkan',
                        'Cuti Alasan Penting' => 'Cuti Alasan Penting',
                        'Cuti Luar Tanggungan' => 'Cuti di Luar Tanggungan Negara',
                        'Cuti Tugas' => 'Cuti Tugas / Dinas Luar',
                        'Cuti Belajar' => 'Cuti / Tugas Belajar',
                        'Izin Lainnya' => 'Izin / Keterangan Lainnya',
                    ])
                    ->required(),

                // ✅ FITUR BARU: Calendar Checkbox untuk Pemilihan Tanggal
                Forms\Components\ViewField::make('tanggal_cuti_array')
                    ->label('Pilih Tanggal Cuti')
                    ->view('forms.components.calendar-checkbox')
                    ->required()
                    ->helperText('Klik tanggal-tanggal yang Anda inginkan untuk cuti. Anda bisa memilih tanggal tidak berurutan.')
                    ->columnSpanFull(),

                // ✅ HIDDEN FIELDS: Untuk kompatibilitas dengan sistem lama
                Forms\Components\Hidden::make('tanggal_mulai')
                    ->default(fn ($get) => !empty($get('tanggal_cuti_array')) 
                        ? min($get('tanggal_cuti_array')) 
                        : null),
                
                Forms\Components\Hidden::make('tanggal_akhir')
                    ->default(fn ($get) => !empty($get('tanggal_cuti_array')) 
                        ? max($get('tanggal_cuti_array')) 
                        : null),

                Textarea::make('alasan')
                    ->label('Alasan Cuti')
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('alamat_selama_cuti')
                    ->label('Alamat Selama Menjalankan Cuti')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('lampiran_link')
                    ->label('Bukti Lampiran (Link Google Drive / Lainnya)')
                    ->placeholder('https://drive.google.com/file/d/...')
                    ->url()
                    ->suffixIcon('heroicon-m-link')
                    ->required(fn (Get $get) => $get('jenis_cuti') === 'Cuti Sakit')
                    ->helperText('Opsional. Wajib diisi (Link Surat Dokter) jika mengambil Cuti Sakit.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.nama')
                    ->label('Nama Pegawai')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => Auth::user()->role !== 'pegawai'),
                    
                TextColumn::make('jenis_cuti')
                    ->label('Jenis Cuti')
                    ->searchable(),
                
                // ✅ UPDATE: Tampilkan tanggal dari array atau fallback ke range
                TextColumn::make('tanggal_cuti')
                    ->label('Tanggal Cuti')
                    ->formatStateUsing(function ($record) {
                        // Jika menggunakan sistem baru (array)
                        if (!empty($record->tanggal_cuti_array) && is_array($record->tanggal_cuti_array)) {
                            $dates = collect($record->tanggal_cuti_array)->sort()->values();
                            
                            if ($dates->count() <= 3) {
                                return $dates->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))->join(', ');
                            }
                            
                            $first = \Carbon\Carbon::parse($dates->first())->format('d/m/Y');
                            $last = \Carbon\Carbon::parse($dates->last())->format('d/m/Y');
                            return "$first ... $last";
                        }
                        
                        // Fallback ke sistem lama (range)
                        if ($record->tanggal_mulai && $record->tanggal_akhir) {
                            $mulai = \Carbon\Carbon::parse($record->tanggal_mulai)->format('d/m');
                            $akhir = \Carbon\Carbon::parse($record->tanggal_akhir)->format('d/m/Y');
                            return "$mulai - $akhir";
                        }
                        
                        return '-';
                    })
                    ->tooltip(function ($record) {
                        if (!empty($record->tanggal_cuti_array) && is_array($record->tanggal_cuti_array)) {
                            return collect($record->tanggal_cuti_array)
                                ->sort()
                                ->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M Y'))
                                ->join(', ');
                        }
                        return null;
                    })
                    ->sortable(),

                // ✅ UPDATE: Hitung lama cuti dari array
                TextColumn::make('lama_cuti')
                    ->label('Lama')
                    ->formatStateUsing(function ($record) {
                        return $record->lama_cuti . ' Hari';
                    })
                    ->sortable()
                    ->alignCenter(),
                    
                BadgeColumn::make('status_global')
                    ->label('Status')
                    ->colors([
                        'warning' => fn ($state) => $state !== 'Disetujui' && $state !== 'Ditolak',
                        'success' => 'Disetujui',
                        'danger' => fn ($state) => str_contains($state, 'Ditolak'),
                ]),
            ])
            ->actions([
            // ✅ FITUR BARU: Lihat Detail Pengajuan
            Tables\Actions\ViewAction::make()
                ->label('Lihat Detail')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalHeading(fn ($record) => 'Detail Pengajuan Cuti - ' . $record->employee->nama)
                ->modalWidth('5xl')
                ->modalContent(fn ($record) => view('filament.resources.cuti.view-detail', ['record' => $record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),

            // Action yang sudah ada sebelumnya
            Action::make('exportFormPdf')
                ->label('Cetak Form PDF')
                ->icon('heroicon-o-printer')
                ->color('danger')
                ->action(function (Cuti $record) {
                    return new StreamedResponse(function () use ($record) {
                        app(FormCutiGenerator::class)->generate($record);
                    }, 200, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="form_cuti.pdf"',
                    ]);
                }),
            ])
            ->actions([
                // === AKSI PDF ===
                Action::make('exportFormPdf')
                    ->label('Cetak Form PDF')
                    ->icon('heroicon-o-printer')
                    ->color('danger')
                    ->action(function (Cuti $record) {
                        return new StreamedResponse(function () use ($record) {
                            app(FormCutiGenerator::class)->generate($record);
                        }, 200, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'inline; filename="form_cuti.pdf"',
                        ]);
                    }),

                // === AKSI TAHAP 1: KETUA TIM / ATASAN LANGSUNG ===
                Action::make('Proses Atasan Langsung')
                    ->label('Proses (Ketua Unit)')
                    ->icon('heroicon-o-pencil')
                    ->color('info')
                    ->visible(function (Cuti $record) {
                        $user = Auth::user();
                        $isKetuaUnit = $record->employee->unitKerja && 
                                       $record->employee->unitKerja->ketua_user_id === $user->id;
                        return $isKetuaUnit && $record->status_global === 'Menunggu Persetujuan Atasan Langsung';
                    })
                    ->form([
                        Select::make('status_atasan_langsung')
                            ->label('Persetujuan Ketua Unit')
                            ->options(['approved' => 'Disetujui', 'rejected' => 'Ditolak'])
                            ->required(),
                        Textarea::make('tanggapan_atasan_langsung')
                            ->label('Catatan / Tanggapan'),
                    ])
                    ->action(function (Cuti $record, array $data): void {
                        $record->status_atasan_langsung = $data['status_atasan_langsung'];
                        $record->tanggapan_atasan_langsung = $data['tanggapan_atasan_langsung'];
                        $record->atasan_langsung_approver_id = Auth::id();

                        if ($data['status_atasan_langsung'] === 'approved') {
                            $record->status_global = 'Menunggu Persetujuan Tata Usaha';
                            
                            // === NOTIFIKASI KE TATA USAHA ===
                            $penerima = \App\Models\User::where('role', 'tata_usaha')->get();
                            
                            Notification::make()
                                ->title('Persetujuan Cuti (Tahap 2)')
                                ->body("Pengajuan {$record->employee->nama} telah disetujui Ketua Tim. Menunggu verifikasi TU.")
                                ->success()
                                ->sendToDatabase($penerima);

                        } else {
                            $namaUnit = $record->employee->unitKerja->nama ?? 'Unit';
                            $record->status_global = "Ditolak (oleh Ketua $namaUnit)";
                            
                            if ($record->employee->user) {
                                Notification::make()
                                    ->title('Pengajuan Ditolak')
                                    ->body("Mohon maaf, pengajuan cuti Anda ditolak oleh Ketua Tim.")
                                    ->danger()
                                    ->sendToDatabase($record->employee->user);
                            }
                        }
                        $record->save();
                    }),

                // === AKSI TAHAP 2: KASUBBAG TATA USAHA ===
                Action::make('Proses Tata Usaha')
                    ->icon('heroicon-o-pencil')
                    ->color('info')
                    ->visible(function (Cuti $record) {
                        return Auth::user()->role === 'tata_usaha' && $record->status_global === 'Menunggu Persetujuan Tata Usaha';
                    })
                    ->form([
                        Select::make('status_tata_usaha')
                            ->label('Persetujuan Kasubbag Tata Usaha')
                            ->options(['approved' => 'Disetujui', 'rejected' => 'Ditolak'])
                            ->required(),
                        Textarea::make('tanggapan_tata_usaha')
                            ->label('Catatan / Tanggapan'),
                    ])
                    ->action(function (Cuti $record, array $data): void {
                        $record->status_tata_usaha = $data['status_tata_usaha'];
                        $record->tanggapan_tata_usaha = $data['tanggapan_tata_usaha'];
                        $record->tata_usaha_approver_id = Auth::id();

                        if ($data['status_tata_usaha'] === 'approved') {
                            $record->status_global = 'Menunggu Persetujuan Kepala';

                            $penerima = \App\Models\User::where('role', 'kepala_stasiun')->get();

                            Notification::make()
                                ->title('Persetujuan Cuti (Final)')
                                ->body("Pengajuan {$record->employee->nama} menunggu persetujuan akhir Anda.")
                                ->info()
                                ->sendToDatabase($penerima);

                        } else {
                            $record->status_global = 'Ditolak (oleh Tata Usaha)';
                            
                            if ($record->employee->user) {
                                Notification::make()
                                    ->title('Pengajuan Ditolak')
                                    ->body("Pengajuan cuti ditolak oleh Tata Usaha. Cek aplikasi untuk detail.")
                                    ->danger()
                                    ->sendToDatabase($record->employee->user);
                            }
                        }
                        $record->save();
                    }),

                // === AKSI TAHAP 3: KEPALA STASIUN ===
                Action::make('Proses Kepala Stasiun')
                    ->icon('heroicon-o-pencil')
                    ->color('info')
                    ->visible(function (Cuti $record) {
                        return Auth::user()->role === 'kepala_stasiun' 
                            && $record->status_global === 'Menunggu Persetujuan Kepala';
                    })
                    ->form([
                        Select::make('status_kepala')
                            ->label('Persetujuan Kepala Stasiun')
                            ->options(['approved' => 'Disetujui', 'rejected' => 'Ditolak'])
                            ->required(),
                        Textarea::make('tanggapan_kepala')
                            ->label('Catatan / Tanggapan'),
                    ])
                    ->action(function (Cuti $record, array $data): void {
                        $record->status_kepala = $data['status_kepala'];
                        $record->tanggapan_kepala = $data['tanggapan_kepala'];
                        $record->kepala_stasiun_approver_id = Auth::id();
                        $employee = $record->employee;

                        if ($data['status_kepala'] === 'approved') {
                            // ✅ UPDATE: Gunakan helper method untuk hitung durasi
                            if ($record->jenis_cuti === 'Cuti Tahunan') {
                                $durasiCuti = $record->lama_cuti; // Helper method dari model
                                
                                if ($employee->sisa_cuti_tahunan >= $durasiCuti) {
                                    $employee->sisa_cuti_tahunan -= $durasiCuti;
                                    $employee->save();
                                }
                            }

                            $record->status_global = 'Disetujui';

                            // === NOTIFIKASI SUKSES KE PEGAWAI ===
                            if ($employee->user) {
                                Notification::make()
                                    ->title('Cuti DISETUJUI! 🎉')
                                    ->body("Selamat, pengajuan cuti Anda selama {$record->lama_cuti} hari telah disetujui Kepala Stasiun. Silakan cetak formulir.")
                                    ->success()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('Cetak PDF')
                                            ->url(route('filament.admin.resources.cutis.index'))
                                    ])
                                    ->sendToDatabase($employee->user);
                            }

                        } else {
                            $record->status_global = 'Ditolak (oleh Kepala Stasiun)';
                            
                            // === NOTIFIKASI DITOLAK KE PEGAWAI ===
                            if ($employee->user) {
                                Notification::make()
                                    ->title('Pengajuan Ditolak')
                                    ->body("Mohon maaf, pengajuan cuti ditolak oleh Kepala Stasiun.")
                                    ->danger()
                                    ->sendToDatabase($employee->user);
                            }
                        }
                        
                        $record->save();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $query = parent::getEloquentQuery();

        switch ($user->role) {
            case 'pegawai':
                if (!$user->employee) { return $query->where('id', 0); }
                return $query->where('employee_id', $user->employee->id);
            
            case 'ketua_tim': 
                $unitKerja = UnitKerja::where('ketua_user_id', $user->id)->first();
                
                if ($unitKerja) {
                    return $query->whereHas('employee', function ($q) use ($unitKerja) {
                        $q->where('unit_kerja_id', $unitKerja->id);
                    })->where('status_global', 'Menunggu Persetujuan Atasan Langsung');
                }
                return $query->where('id', 0); 

            case 'tata_usaha':
                return $query->where('status_global', 'Menunggu Persetujuan Tata Usaha');
            
            case 'kepala_stasiun':
                return $query->where('status_global', 'Menunggu Persetujuan Kepala');

            case 'admin':
                return $query;
            
            default:
                return $query->where('id', 0);
        }
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCutis::route('/'),
            'create' => Pages\CreateCuti::route('/create'),
            'edit' => Pages\EditCuti::route('/{record}/edit'),
        ];
    }
}