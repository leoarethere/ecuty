<?php

namespace App\Services;

use TCPDF;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Class RekapCutiGenerator
 *
 * Menggunakan TCPDF untuk membuat file PDF rekap (tabel)
 * yang dinamis dan rapi.
 */
class RekapCutiGenerator extends TCPDF
{
    protected Collection $records;

    public function generate(Collection $records)
    {
        $this->records = $records;

        // --- Konfigurasi Halaman ---
        $this->SetAuthor('Sistem E-Cuti TVRI Yogyakarta');
        $this->SetTitle('Rekap Pengajuan Cuti');
        $this->SetSubject('Rekap Data Cuti');
        
        // Margin: Kiri, Atas, Kanan (10mm agar muat tabel lebar)
        $this->SetMargins(10, 15, 10);
        $this->SetAutoPageBreak(true, 15); // Margin bawah 15mm
        
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        
        $this->AddPage('L', 'A4'); // Landscape

        // --- Mulai Menggambar ---
        $this->SetFont('times', '', 11);
        $this->drawTitle();
        $this->drawTable(); 

        // --- Output ---
        $fileName = 'rekap_cuti_' . date('Ymd_His') . '.pdf';
        $this->Output($fileName, 'I'); 
    }

    /**
     * Menggambar Judul Rekap
     */
    private function drawTitle()
    {
        $this->SetFont('times', 'B', 14);
        $this->Cell(0, 10, 'REKAP DATA PENGAJUAN CUTI', 0, 1, 'C');
        $this->SetFont('times', 'B', 14);
        $this->Cell(0, 8, 'LEMBAGA PENYIARAN PUBLIK TELEVISI REPUBLIK INDONESIA', 0, 1, 'C');
        $this->SetFont('times', 'B', 14);
        $this->Cell(0, 8, 'STASIUN YOGYAKARTA', 0, 1, 'C');
        $this->SetFont('times', 'I', 12);
        $this->Cell(0, 7, 'Periode Cetak: ' . Carbon::now()->isoFormat('D MMMM Y'), 0, 1, 'C');
        $this->Ln(5);
    }

    /**
     * Menggambar Header Tabel
     */
    private function drawTableHeader($header, $w)
    {
        $this->SetFont('times', 'B', 9);
        $this->SetFillColor(220, 220, 220); // Background abu-abu
        
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
    }

    /**
     * Menggambar Tabel Data
     */
    private function drawTable()
    {
        // === KONFIGURASI TABEL ===
        $this->SetFont('times', '', 9);
        $this->setCellPaddings(1.5, 1.5, 1.5, 1.5);

        // Total Lebar A4 Landscape (297mm) - Margin (20mm) = 277mm
        $w = [
            10, // No
            35, // NIP
            45, // Nama
            35, // Unit
            27, // Jenis
            20, // Mulai
            20, // Akhir
            15, // Lama
            40, // Alasan
            30  // Status
        ];
        
        $header = ['No', 'NIP', 'Nama Pegawai', 'Unit Kerja', 'Jenis Cuti', 
                   'Mulai', 'Akhir', 'Hari', 'Alasan', 'Status'];

        // Cetak Header Pertama Kali
        $this->drawTableHeader($header, $w);

        // === CETAK DATA ===
        $this->SetFont('times', '', 9);
        $this->SetFillColor(255, 255, 255); // Reset warna fill putih
        
        $no = 1;
        foreach ($this->records as $cuti) {
            // Pastikan $cuti adalah object/model yang valid
            if (!is_object($cuti)) {
                continue;
            }
            
            // Hitung lama cuti (antisipasi jika data null)
            $lamaCuti = 0;
            if (isset($cuti->tanggal_mulai) && isset($cuti->tanggal_akhir)) {
                $lamaCuti = Carbon::parse($cuti->tanggal_mulai)
                            ->diffInDays(Carbon::parse($cuti->tanggal_akhir)) + 1;
            }

            // === PERBAIKAN: aman terhadap relasi / field yang null ===
            $employee = isset($cuti->employee) ? $cuti->employee : null;
            $nip = $employee && isset($employee->NIP) ? $employee->NIP : '-';
            $namaPegawai = $employee && isset($employee->nama) ? $employee->nama : '-';
            
            // Cek apakah ada relasi unitKerja atau langsung field unit_kerja
            if ($employee && isset($employee->unitKerja) && isset($employee->unitKerja->nama)) {
                $unitKerjaNama = $employee->unitKerja->nama;
            } elseif ($employee && isset($employee->unit_kerja)) {
                $unitKerjaNama = $employee->unit_kerja;
            } else {
                $unitKerjaNama = '-';
            }

            $tanggalMulai = isset($cuti->tanggal_mulai) && $cuti->tanggal_mulai 
                ? Carbon::parse($cuti->tanggal_mulai)->format('d/m/Y') 
                : '-';
            $tanggalAkhir = isset($cuti->tanggal_akhir) && $cuti->tanggal_akhir 
                ? Carbon::parse($cuti->tanggal_akhir)->format('d/m/Y') 
                : '-';

            // Siapkan data baris (pastikan semua elemen stringable)
            $jenisCuti = isset($cuti->jenis_cuti) ? $cuti->jenis_cuti : '-';
            $alasan = isset($cuti->alasan) ? $cuti->alasan : '-';
            $statusGlobal = isset($cuti->status_global) ? $cuti->status_global : '-';
            
            $data = [
                (string) $no++,
                (string) $nip,
                (string) $namaPegawai,
                (string) $unitKerjaNama,
                (string) $jenisCuti,
                (string) $tanggalMulai,
                (string) $tanggalAkhir,
                (string) $lamaCuti,
                (string) $alasan,
                (string) $statusGlobal
            ];
            
            $align = ['C', 'L', 'L', 'L', 'L', 'C', 'C', 'C', 'L', 'L'];

            // 1. Hitung tinggi baris dinamis
            $maxHeight = $this->calculateRowHeight($data, $w);

            // 2. LOGIKA PAGE BREAK PINTAR
            if ($this->GetY() + $maxHeight > ($this->getPageHeight() - $this->getBreakMargin())) {
                $this->AddPage('L', 'A4');
                $this->drawTableHeader($header, $w); // Gambar header lagi
                $this->SetFont('times', '', 9);      // Kembalikan font data
            }

            // 3. Gambar sel dengan posisi X yang tepat
            $x = $this->GetX();
            $y = $this->GetY();
            
            for ($i = 0; $i < count($data); ++$i) {
                $this->SetXY($x, $y);
                
                $this->MultiCell(
                    $w[$i],      // Lebar
                    $maxHeight,  // Tinggi
                    $data[$i],   // Data
                    1,           // Border
                    $align[$i],  // Align
                    false,       // Fill
                    0,           // New line (0 = kanan, bukan bawah)
                    '', '', true, 0, false, true, 
                    $maxHeight,  
                    'M'          // Valign Middle
                );
                
                $x += $w[$i]; // Pindah ke kolom berikutnya
            }
            
            $this->Ln(); // Pindah baris setelah semua kolom
        }
    }

    /**
     * Menghitung tinggi baris berdasarkan kolom dengan teks terbanyak
     */
    private function calculateRowHeight($data, $widths)
    {
        $nb = 0;
        // TCPDF mengembalikan jumlah baris teks yang akan dicetak
        for ($i = 0; $i < count($data); $i++) {
            $text = (string) $data[$i];
            $nb = max($nb, $this->getNumLines($text, $widths[$i]));
        }
        
        // Tinggi per baris text (sekitar 5mm untuk font size 9)
        $h = 5 * $nb; 
        
        // Tambahkan sedikit padding vertikal (2mm) agar tidak terlalu sesak
        return max($h + 2, 8); // Minimal 8mm
    }

    /**
     * Footer Halaman
     */
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('times', 'I', 8);
        // Garis pemisah footer
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Ln(2);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}