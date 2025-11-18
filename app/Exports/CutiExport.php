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
            ['LPP TVRI STASIUN YOGYAKARTA'],
            ['Periode: ' . Carbon::now()->format('d F Y')],
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
        // Hitung lama cuti (opsional, sesuaikan dengan kebutuhan kolommu)
        $lamaCuti = \Carbon\Carbon::parse($cuti->tanggal_mulai)
            ->diffInDays(\Carbon\Carbon::parse($cuti->tanggal_akhir)) + 1;

        return [
            // Kolom 1: No (Bisa pakai $cuti->id atau counter manual jika ada)
            $cuti->id, 

            // Kolom 2: NIP (INI PERBAIKANNYA)
            // Tambahkan spasi kosong di depan agar dibaca sebagai teks oleh Excel
            " " . ($cuti->employee->NIP ?? '-'), 
            
            // Kolom 3: Nama
            $cuti->employee->nama ?? '-',
            
            // Kolom 4: Unit Kerja
            $cuti->employee->unitKerja->nama ?? '-',
            
            // Kolom 5: Jenis Cuti
            $cuti->jenis_cuti,
            
            // Kolom 6: Tgl Mulai
            $cuti->tanggal_mulai,
            
            // Kolom 7: Tgl Akhir
            $cuti->tanggal_akhir,
            
            // Kolom 8: Lama (Hari)
            $lamaCuti . ' Hari',
            
            // Kolom 9: Alasan
            $cuti->alasan,
            
            // Kolom 10: Status
            $cuti->status_global,
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
            'M' => 18,  // Tgl Diajukan
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
                'font' => [
                    'bold' => true,
                    'size' => 14,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],
            2 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],
            3 => [
                'font' => [
                    'size' => 10,
                    'italic' => true,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],
            
            // Style untuk header tabel (row 5)
            5 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
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
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);
                    
                    // Alignment untuk data
                    $sheet->getStyle("A6:A{$highestRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
                    $sheet->getStyle("B6:B{$highestRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER); // NIP
                    $sheet->getStyle("G6:I{$highestRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER); // Tanggal & Lama
                    $sheet->getStyle("L6:M{$highestRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER); // Status & Tgl Diajukan
                    
                    // Wrap text untuk kolom panjang
                    $sheet->getStyle("J6:K{$highestRow}")->getAlignment()->setWrapText(true);
                    
                    // Set row height untuk data
                    for ($row = 6; $row <= $highestRow; $row++) {
                        $sheet->getRowDimension($row)->setRowHeight(20);
                    }
                    
                    // Zebra striping (warna selang-seling)
                    for ($row = 6; $row <= $highestRow; $row++) {
                        if ($row % 2 == 0) {
                            $sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F2F2F2'],
                                ],
                            ]);
                        }
                    }
                }
                
                // Auto-fit semua kolom (opsional, hapus jika sudah ada columnWidths)
                // foreach(range('A','M') as $col) {
                //     $sheet->getColumnDimension($col)->setAutoSize(true);
                // }
            },
        ];
    }
}