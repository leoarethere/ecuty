<?php

namespace App\Exports;

use Carbon\Carbon;
use App\Models\Cuti;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CutiExport implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithStyles, 
    WithColumnWidths,
    WithEvents
{
    protected Collection $records;

    public function __construct(Collection $records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records;
    }

    /**
     * Heading untuk Excel/PDF
     */
    public function headings(): array
    {
        return [
            ['REKAP DATA PENGAJUAN CUTI'],
            ['LEMBAGA PENYIARAN PUBLIK TELEVISI REPUBLIK INDONESIA'],
            ['STASIUN YOGYAKARTA'],
            ['Periode: ' . Carbon::now()->isoFormat('D MMMM Y')],
            [], // Baris kosong
            [
                'No',
                'NIP',
                'Nama Pegawai',
                'Jabatan',
                'Unit Kerja',
                'Jenis Cuti',
                'Tanggal Mulai',
                'Tanggal Akhir',
                'Lama (Hari)',
                'Alasan',
                'Alamat Selama Cuti',
                'Status',
                'Tanggal Diajukan'
            ]
        ];
    }

    /**
    * Memetakan data untuk setiap baris
    */
    public function map($cuti): array
    {
        // === LOGIKA BARU: HITUNG LAMA CUTI YANG AKURAT ===
        // Prioritaskan kolom 'lama_cuti' dari database.
        // Jika kosong (data lama), baru hitung manual dari selisih tanggal.
        $lamaCuti = 0;
        
        if (isset($cuti->lama_cuti) && $cuti->lama_cuti > 0) {
            $lamaCuti = $cuti->lama_cuti;
        } elseif (isset($cuti->tanggal_mulai) && isset($cuti->tanggal_akhir)) {
            $lamaCuti = \Carbon\Carbon::parse($cuti->tanggal_mulai)
                ->diffInDays(\Carbon\Carbon::parse($cuti->tanggal_akhir)) + 1;
        }
        // ================================================

        // Ambil nama unit kerja dengan aman
        $unitKerja = '-';
        if ($cuti->employee && $cuti->employee->unitKerja) {
            $unitKerja = $cuti->employee->unitKerja->nama;
        } elseif ($cuti->employee && $cuti->employee->unit_kerja) {
            $unitKerja = $cuti->employee->unit_kerja; // Fallback kolom lama
        }

        return [
            // Kolom 1: No (ID Cuti)
            $cuti->id, 

            // Kolom 2: NIP (Tambahkan spasi agar jadi text)
            " " . ($cuti->employee->NIP ?? '-'), 
            
            // Kolom 3: Nama
            $cuti->employee->nama ?? '-',
            
            // Kolom 4: Jabatan
            $cuti->employee->jabatan ?? '-',

            // Kolom 5: Unit Kerja
            $unitKerja,
            
            // Kolom 6: Jenis Cuti
            $cuti->jenis_cuti,
            
            // Kolom 7: Tgl Mulai
            $cuti->tanggal_mulai ? \Carbon\Carbon::parse($cuti->tanggal_mulai)->format('d/m/Y') : '-',
            
            // Kolom 8: Tgl Akhir
            $cuti->tanggal_akhir ? \Carbon\Carbon::parse($cuti->tanggal_akhir)->format('d/m/Y') : '-',
            
            // Kolom 9: Lama (Hari) -> SUDAH DIPERBAIKI
            $lamaCuti . ' Hari',
            
            // Kolom 10: Alasan
            $cuti->alasan,

            // Kolom 11: Alamat
            $cuti->alamat_selama_cuti,
            
            // Kolom 12: Status
            $cuti->status_global,

            // Kolom 13: Tanggal Diajukan
            \Carbon\Carbon::parse($cuti->created_at)->format('d/m/Y'),
        ];
    }

    /**
     * Lebar kolom
     */
    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 18,  // NIP
            'C' => 25,  // Nama
            'D' => 20,  // Jabatan
            'E' => 20,  // Unit Kerja
            'F' => 20,  // Jenis Cuti
            'G' => 12,  // Tgl Mulai
            'H' => 12,  // Tgl Akhir
            'I' => 10,  // Lama
            'J' => 30,  // Alasan
            'K' => 30,  // Alamat
            'L' => 25,  // Status
            'M' => 15,  // Tgl Diajukan
        ];
    }

    /**
     * Style untuk sheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style untuk judul (row 1-3)
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            2 => [
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            3 => [
                'font' => ['size' => 10, 'italic' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            
            // Style untuk header tabel (row 5)
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
        ];
    }

    /**
     * Event setelah sheet dibuat
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Merge cells untuk judul
                $sheet->mergeCells('A1:M1');
                $sheet->mergeCells('A2:M2');
                $sheet->mergeCells('A3:M3');
                
                // Set height untuk header
                $sheet->getRowDimension(5)->setRowHeight(30);
                
                // Wrap text untuk header
                $sheet->getStyle('A5:M5')->getAlignment()->setWrapText(true);
                
                // Style untuk data rows (mulai dari row 6)
                $highestRow = $sheet->getHighestRow();
                
                if ($highestRow > 5) {
                    // Border untuk semua data
                    $sheet->getStyle("A5:M{$highestRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);
                    
                    // Alignment Center untuk kolom tertentu
                    // A(No), B(NIP), F(Jenis), G(Mulai), H(Akhir), I(Lama), L(Status), M(Diajukan)
                    $centerColumns = ['A', 'B', 'G', 'H', 'I', 'L', 'M'];
                    foreach ($centerColumns as $col) {
                        $sheet->getStyle("{$col}6:{$col}{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                    
                    // Wrap text untuk kolom panjang (Alasan & Alamat)
                    $sheet->getStyle("J6:K{$highestRow}")->getAlignment()->setWrapText(true);
                    
                    // Set row height
                    for ($row = 6; $row <= $highestRow; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(20);
                    }
                    
                    // Zebra striping
                    for ($row = 6; $row <= $highestRow; $row++) {
                        if ($row % 2 == 0) {
                            $sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
                            ]);
                        }
                    }
                }
            },
        ];
    }
}