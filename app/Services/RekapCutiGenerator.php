<?php

namespace App\Services;

use TCPDF;
use Carbon\Carbon;
use App\Models\Cuti;
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

    private function drawTitle()
    {
        $this->SetFont('times', 'B', 16);
        $this->Cell(0, 10, 'REKAP DATA PENGAJUAN CUTI', 0, 1, 'C');
        $this->SetFont('times', 'B', 14);
        $this->Cell(0, 8, 'LEMBAGA PENYIARAN PUBLIK TVRI STASIUN YOGYAKARTA', 0, 1, 'C');
        $this->SetFont('times', 'I', 12);
        // Pastikan locale Carbon sudah 'id' di AppServiceProvider agar tanggal Indo
        $this->Cell(0, 7, 'Periode Cetak: ' . Carbon::now()->isoFormat('D MMMM Y'), 0, 1, 'C');
        $this->Ln(8);
    }

    /**
     * Fungsi khusus menggambar Header Tabel
     * Dipisahkan agar mudah dipanggil ulang saat ganti halaman
     */
    private function drawTableHeader($header, $w)
    {
        $this->SetFont('times', 'B', 9); // Sedikit diperbesar dari 8
        $this->SetFillColor(230, 230, 230); // Abu-abu muda
        $this->SetTextColor(0, 0, 0);
        $this->SetLineWidth(0.1);

        $num_headers = count($header);
        for ($i = 0; $i < $num_headers; ++$i) {
            $this->MultiCell(
                $w[$i],     // Lebar
                10,         // Tinggi Header
                $header[$i],// Teks
                1,          // Border
                'C',        // Align Center
                true,       // Fill Background
                0,          // Pindah baris (0 = kanan)
                '', '', true, 0, false, true, 10, 'M' // Valign Middle
            );
        }
        $this->Ln();
    }

    private function drawTable()
    {
        // === KONFIGURASI TABEL ===
        $this->SetFont('times', '', 9);
        $this->SetCellPadding(1.5); // Memberi jarak teks dari garis agar tidak mepet

        // Total Lebar A4 Landscape (297mm) - Margin (20mm) = 277mm
        // Array lebar kolom disesuaikan agar total pas 277
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
            
            // Hitung lama cuti (antisipasi jika data null)
            $lamaCuti = 0;
            if ($cuti->tanggal_mulai && $cuti->tanggal_akhir) {
                $lamaCuti = Carbon::parse($cuti->tanggal_mulai)
                            ->diffInDays(Carbon::parse($cuti->tanggal_akhir)) + 1;
            }

            // Siapkan data baris
            $data = [
                $no++,
                $cuti->employee->NIP ?? '-',
                $cuti->employee->nama ?? '-',
                $cuti->employee->unit_kerja ?? '-', // Pastikan relasi/kolom ini benar
                $cuti->jenis_cuti,
                Carbon::parse($cuti->tanggal_mulai)->format('d/m/Y'),
                Carbon::parse($cuti->tanggal_akhir)->format('d/m/Y'),
                $lamaCuti,
                $cuti->alasan,
                $cuti->status_global
            ];
            
            $align = ['C', 'L', 'L', 'L', 'L', 'C', 'C', 'C', 'L', 'L'];

            // 1. Hitung tinggi baris dinamis berdasarkan konten terpanjang
            $maxHeight = $this->calculateRowHeight($data, $w);

            // 2. LOGIKA PAGE BREAK PINTAR
            // Cek apakah sisa halaman cukup untuk menampung baris ini?
            // GetPageHeight() = 210mm (A4). GetBreakMargin() = 15mm.
            if ($this->GetY() + $maxHeight > ($this->GetPageHeight() - $this->getBreakMargin())) {
                $this->AddPage('L', 'A4');
                $this->drawTableHeader($header, $w); // Gambar header lagi
                $this->SetFont('times', '', 9);      // Kembalikan font data
            }

            // 3. Gambar sel
            for ($i = 0; $i < count($data); ++$i) {
                $this->MultiCell(
                    $w[$i],      // Lebar
                    $maxHeight,  // Tinggi (Semua sel tingginya sama di baris ini)
                    $data[$i],   // Data
                    1,           // Border
                    $align[$i],  // Align
                    false,       // Fill
                    0,           // New line (0 = lanjut kanan)
                    '', '', true, 0, false, true, 
                    $maxHeight,  // Max Height limit
                    'M'          // Valign Middle (Teks di tengah vertikal)
                );
            }
            $this->Ln(); // Pindah ke baris berikutnya
        }
    }

    /**
     * Menghitung tinggi baris berdasarkan kolom dengan teks terbanyak
     */
    private function calculateRowHeight($data, $widths)
    {
        $nb = 0;
        // TCPDF mengembalikan jumlah baris teks yang akan dicetak (NbLines)
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->getNumLines($data[$i], $widths[$i]));
        }
        
        // Tinggi per baris text (sekitar 5mm untuk font size 9)
        $h = 5 * $nb; 
        
        // Tambahkan sedikit padding vertikal (2mm) agar tidak terlalu sesak
        return $h + 2; 
    }

    /**
     * Footer Halaman
     */
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('times', 'I', 8);
        // Garis pemisah footer
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}