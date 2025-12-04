<?php

namespace App\Services;

use TCPDF;
use Carbon\Carbon;
use App\Models\Cuti;

/**
 * Class FormCutiGenerator
 *
 * Menggunakan TCPDF untuk "menggambar" PDF formulir cuti individual
 * dengan tampilan yang lebih rapi dan profesional
 */
class FormCutiGenerator extends TCPDF
{
    protected Cuti $cuti;
    protected $masaKerja;
    protected $lamaCuti;

    /**
     * Fungsi utama untuk menghasilkan PDF
     */
    public function generate(Cuti $cuti)
    {
        // ✅ Eager load SEMUA relasi yang dibutuhkan
        $this->cuti = $cuti->load([
            'employee',
            'kepalaApprover.employee',
            'tataUsahaApprover.employee',
            'atasanLangsungApprover.employee'
        ]);

        // Hitung data tambahan
        $this->calculateData();

        // --- Konfigurasi Halaman ---
        $this->SetAuthor('Sistem E-Cuti TVRI Yogyakarta');
        $this->SetTitle('Form Cuti - ' . $this->cuti->employee->nama);
        $this->SetSubject('Formulir Pengajuan Cuti');
        
        $this->SetMargins(20, 15, 20);
        $this->SetAutoPageBreak(true, 20);
        
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        
        $this->AddPage('P', 'A4');

        // --- Mulai Menggambar PDF ---
        $this->SetFont('helvetica', '', 10);

        $this->drawHeader();
        $this->drawTable();

        // --- Selesai & Output ---
        $fileName = 'Pengajuan_Cuti_Pegawai_' . str_replace(' ', '_', $this->cuti->employee->nama) . '_' . date('Ymd') . '.pdf';
        
        $this->Output($fileName, 'I'); 
    }

    /**
     * Menghitung data dinamis (Masa Kerja & Lama Cuti)
     */
    private function calculateData()
    {
        // 1. Hitung masa kerja
        if ($this->cuti->employee->tanggal_bergabung) {
            $tanggalBergabung = Carbon::parse($this->cuti->employee->tanggal_bergabung);
            $years = $tanggalBergabung->diffInYears(now());
            $months = $tanggalBergabung->copy()->addYears($years)->diffInMonths(now());
            
            if ($years > 0) {
                $this->masaKerja = $years . ' Tahun' . ($months > 0 ? ' ' . $months . ' Bulan' : '');
            } else {
                $this->masaKerja = $months . ' Bulan';
            }
        } else {
            $this->masaKerja = '-';
        }
            
        // 2. LOGIKA BARU: Hitung Lama Cuti
        // Prioritaskan kolom 'lama_cuti' dari database agar akurat dengan jumlah hari yang diceklis.
        // Jika null (data lama), baru hitung manual dari selisih tanggal.
        if (isset($this->cuti->lama_cuti) && $this->cuti->lama_cuti > 0) {
            $this->lamaCuti = $this->cuti->lama_cuti;
        } else {
            $this->lamaCuti = Carbon::parse($this->cuti->tanggal_mulai)
                                ->diffInDays(Carbon::parse($this->cuti->tanggal_akhir)) + 1;
        }
    }

    /**
     * Menggambar Header dengan Logo dan Kop Surat
     */
    private function drawHeader()
    {
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY(20, 20);
        $this->Cell(0, 8, 'LEMBAGA PENYIARAN PUBLIK TELEVISI REPUBLIK INDONESIA', 0, 1, 'C');
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 8, 'STASIUN YOGYAKARTA', 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, 'Jl. Magelang Km. 4,5 Daerah Istimewa Yogyakarta 55284 | Telp: (0274) 514909 | Email: yogyakarta@tvri.go.id', 0, 1, 'C');
        
        // Garis pemisah
        $this->SetLineWidth(0.8);
        $this->Line(20, $this->GetY() + 3, 190, $this->GetY() + 3);
        $this->SetLineWidth(0.2);
        $this->Line(20, $this->GetY() + 4, 190, $this->GetY() + 4);
        
        $this->Ln(10);
        
        // Judul Formulir
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 8, 'FORMULIR PERMINTAAN DAN PEMBERIAN CUTI PEGAWAI', 0, 1, 'C');
        $this->Ln(5);
    }

    private function drawTable()
    {
        $this->SetFont('helvetica', '', 10);

        // --- Persiapan Data Kepala Stasiun ---
        $kepala = $this->cuti->kepalaApprover;
        $signaturePath = $kepala ? $kepala->signature_image_path : null;
        $kepalaNama = '[Nama Kepala Stasiun]';
        $kepalaNip = '[NIP Kepala Stasiun]';
        if ($kepala && $kepala->employee) {
            $kepalaNama = $kepala->employee->nama;
            $kepalaNip = $kepala->employee->NIP;
        }

        // --- Persiapan Data Tata Usaha ---
        $tataUsaha = $this->cuti->tataUsahaApprover;
        $tataUsahaSignaturePath = $tataUsaha ? $tataUsaha->signature_image_path : null;
        $tataUsahaNama = '[Nama Kasubbag Tata Usaha]';
        $tataUsahaNip = '[NIP Kasubbag Tata Usaha]';
        if ($tataUsaha && $tataUsaha->employee) {
            $tataUsahaNama = $tataUsaha->employee->nama;
            $tataUsahaNip = $tataUsaha->employee->NIP;
        }

        // Ambil Nama Unit Kerja
        $unitKerja = $this->cuti->employee->unitKerja; 
        $namaUnit = $unitKerja ? $unitKerja->nama : '-'; 

        // --- HTML Tanda Tangan ---
        $kepalaSignatureHtml = '<div style="color: #999; font-style: italic; font-size: 9pt;">TTD Digital</div>';
        if ($this->cuti->status_kepala == 'approved' && $signaturePath && file_exists(storage_path('app/public/' . $signaturePath))) {
            $kepalaSignatureHtml = '<img src="' . storage_path('app/public/' . $signaturePath) . '" style="height: 20mm; width: auto; max-height: 20mm;">';
        }

        $tataUsahaSignatureHtml = '<div style="color: #999; font-style: italic; font-size: 9pt;">TTD Digital</div>';
        if ($this->cuti->status_tata_usaha == 'approved' && $tataUsahaSignaturePath && file_exists(storage_path('app/public/' . $tataUsahaSignaturePath))) {
            $tataUsahaSignatureHtml = '<img src="' . storage_path('app/public/' . $tataUsahaSignaturePath) . '" style="height: 20mm; width: auto; max-height: 20mm;">';
        }

        // Mapping jenis cuti
        $jenisMap = [
            'Cuti Tahunan' => 'Cuti Tahunan',
            'Cuti Sakit' => 'Cuti Sakit',
            'Cuti Besar' => 'Cuti Besar',
            'Cuti Melahirkan' => 'Cuti Melahirkan',
            'Cuti Karena Alasan Penting' => 'Cuti Karena Alasan Penting'
        ];
        $jenisCuti = $jenisMap[$this->cuti->jenis_cuti] ?? $this->cuti->jenis_cuti;

        // Status styling
        $statusLabels = [
            'pending' => 'MENUNGGU',
            'approved' => 'DISETUJUI',
            'rejected' => 'DITOLAK'
        ];
        
        $statusAtasan = $this->cuti->status_atasan_langsung; 
        $tanggapanAtasan = $this->cuti->tanggapan_atasan_langsung;

        // ========================================
        // ✅ FORMAT TANGGAL CUTI (BARU!)
        // ========================================
        $tanggalCutiHtml = '-';
        $periodeCutiHtml = '-';

        if (!empty($this->cuti->tanggal_cuti_array) && is_array($this->cuti->tanggal_cuti_array)) {
            // Sistem BARU: Array tanggal
            $dates = collect($this->cuti->tanggal_cuti_array)->sort()->values();
            
            // Format detail untuk tabel
            if ($dates->count() <= 10) {
                // Jika sedikit, tampilkan semua
                $tanggalCutiHtml = $dates->map(function($d) {
                    return \Carbon\Carbon::parse($d)->isoFormat('D MMM Y');
                })->join(', ');
            } else {
                // Jika banyak, grup per bulan
                $grouped = $dates->groupBy(function($d) {
                    return \Carbon\Carbon::parse($d)->format('Y-m');
                });
                
                $tanggalCutiHtml = $grouped->map(function($monthDates, $month) {
                    $monthName = \Carbon\Carbon::parse($monthDates->first())->isoFormat('MMMM Y');
                    $days = $monthDates->map(fn($d) => \Carbon\Carbon::parse($d)->day)->join(', ');
                    return "Tanggal $days $monthName";
                })->join('<br>');
            }
            
            // Periode untuk ringkasan
            $first = \Carbon\Carbon::parse($dates->first())->isoFormat('D MMMM Y');
            $last = \Carbon\Carbon::parse($dates->last())->isoFormat('D MMMM Y');
            $periodeCutiHtml = "<strong>$first</strong> s/d <strong>$last</strong><br><em style='font-size: 9pt; color: #6b7280;'>(Total: {$dates->count()} hari yang dipilih)</em>";
            
        } elseif ($this->cuti->tanggal_mulai && $this->cuti->tanggal_akhir) {
            // Sistem LAMA: Range tanggal
            $periodeCutiHtml = '<strong>'.Carbon::parse($this->cuti->tanggal_mulai)->isoFormat('D MMMM Y').'</strong> s/d <strong>'.Carbon::parse($this->cuti->tanggal_akhir)->isoFormat('D MMMM Y').'</strong>';
            $tanggalCutiHtml = $periodeCutiHtml;
        }

        // Lama cuti (gunakan helper dari model)
        $lamaCutiHtml = $this->lamaCuti . ' Hari';

        $html = '
        <style>
            .form-table { border-collapse: collapse; width: 100%; font-family: helvetica; }
            .form-table td { padding: 4px 10px; vertical-align: top; line-height: 1.5; }
            .section-header {
                background: linear-gradient(to right, #1e3a8a, #3b82f6);
                color: white; font-weight: bold; font-size: 11pt; padding: 10px !important;
                text-transform: uppercase; letter-spacing: 0.5px;
            }
            .label-cell { width: 35%; color: #374151; font-weight: 600; border-right: 1px solid #e5e7eb; }
            .value-cell { width: 65%; color: #1f2937; background-color: #f9fafb; }
            .row-separator { border-bottom: 1px solid #e5e7eb; }
            .status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 9pt; }
            .status-approved { background-color: #d1fae5; color: #065f46; }
            .status-pending { background-color: #fef3c7; color: #92400e; }
            .status-rejected { background-color: #fee2e2; color: #991b1b; }
            .signature-section { margin-top: 30px; }
            .signature-table { width: 100%; border: none; border-collapse: collapse; margin-top: 5px; }
            .signature-table td { border: none; padding: 0; text-align: center; }
            .signature-box { text-align: center; margin: 15px auto; min-height: 40px; }
            .signature-title { margin-bottom: 2px; font-weight: bold; color: #374151; font-size: 11pt; }
            .signature-role { margin-bottom: 15px; color: #6b7280; font-size: 10pt; }
            .signature-name { font-weight: bold; margin-top: 5px; font-size: 11pt; }
            .signature-nip { margin-top: 2px; font-size: 9pt; }
            .important-note { background-color: #fef9c3; border-left: 4px solid #eab308; padding: 10px; margin: 15px 0; font-size: 9pt; color: #854d0e; }
            .date-row { text-align: center; margin-bottom: 20px; font-size: 10pt; color: #374151; }
        </style>

        <table class="form-table" cellpadding="0" cellspacing="0">
            <tr>
                <td colspan="2" class="section-header"><span style="margin-right: 8px;">📋</span> I. DATA PEGAWAI</td>
            </tr>
            <tr class="row-separator"><td class="label-cell">Nama Lengkap</td><td class="value-cell"><strong>'.e($this->cuti->employee->nama).'</strong></td></tr>
            <tr class="row-separator"><td class="label-cell">Nomor Induk Pegawai (NIP)</td><td class="value-cell">'.e($this->cuti->employee->NIP).'</td></tr>
            <tr class="row-separator"><td class="label-cell">Jabatan</td><td class="value-cell">'.e($this->cuti->employee->jabatan).'</td></tr>
            <tr class="row-separator"><td class="label-cell">Unit Kerja</td><td class="value-cell">'.e($namaUnit).'</td></tr>
            <tr class="row-separator"><td class="label-cell">Nomor Telepon</td><td class="value-cell">'.e($this->cuti->employee->telp ?? '-').'</td></tr>
            <tr class="row-separator"><td class="label-cell">Masa Kerja</td><td class="value-cell">'.e($this->masaKerja).'</td></tr>
            <tr><td class="label-cell">Sisa Cuti Tahunan</td><td class="value-cell"><strong style="color: #1e40af;">'.e($this->cuti->employee->sisa_cuti_tahunan).' Hari</strong></td></tr>

            <tr>
                <td colspan="2" class="section-header"><span style="margin-right: 8px;">📅</span> II. DATA PENGAJUAN CUTI</td>
            </tr>
            <tr class="row-separator"><td class="label-cell">Tanggal Pengajuan</td><td class="value-cell">'.Carbon::parse($this->cuti->created_at)->isoFormat('dddd, D MMMM Y').'</td></tr>
            <tr class="row-separator"><td class="label-cell">Jenis Cuti</td><td class="value-cell"><strong>'.e($jenisCuti).'</strong></td></tr>
            <tr class="row-separator"><td class="label-cell">Alasan Cuti</td><td class="value-cell">'.e($this->cuti->alasan).'</td></tr>
            
            <tr class="row-separator"><td class="label-cell">Periode Cuti</td><td class="value-cell">'.$periodeCutiHtml.'</td></tr>
            <tr class="row-separator"><td class="label-cell">Detail Tanggal Cuti</td><td class="value-cell" style="font-size: 9pt;">'.$tanggalCutiHtml.'</td></tr>
            <tr class="row-separator"><td class="label-cell">Total Hari Cuti</td><td class="value-cell"><strong style="color: #1e40af;">'.e($lamaCutiHtml).'</strong></td></tr>
            
            <tr><td class="label-cell">Alamat Selama Cuti</td><td class="value-cell">'.nl2br(e($this->cuti->alamat_selama_cuti)).'</td></tr>

            <tr>
                <td colspan="2" class="section-header"><span style="margin-right: 8px;">✓</span> III. STATUS PERSETUJUAN</td>
            </tr>
            <tr class="row-separator">
                <td class="label-cell">Ketua '.e($namaUnit).'</td>
                <td class="value-cell">
                    <span class="status-badge status-'.strtolower($statusAtasan).'">
                        '. ($statusAtasan == 'pending' ? 'MENUNGGU PERSETUJUAN' : e($statusLabels[$statusAtasan] ?? strtoupper($statusAtasan))) .'
                    </span>
                    '.($tanggapanAtasan ? '<br><em style="color: #6b7280; font-size: 9pt;">Catatan: '.e($tanggapanAtasan).'</em>' : '').'
                </td>
            </tr>
            <tr class="row-separator">
                <td class="label-cell">Kasubbag Tata Usaha</td>
                <td class="value-cell">
                    <span class="status-badge status-'.strtolower($this->cuti->status_tata_usaha).'">
                        '. ($this->cuti->status_tata_usaha == 'pending' ? 'MENUNGGU PERSETUJUAN' : e($statusLabels[$this->cuti->status_tata_usaha] ?? strtoupper($this->cuti->status_tata_usaha))) .'
                    </span>
                    '.($this->cuti->tanggapan_tata_usaha ? '<br><em style="color: #6b7280; font-size: 9pt;">Catatan: '.e($this->cuti->tanggapan_tata_usaha).'</em>' : '').'
                </td>
            </tr>
            <tr>
                <td class="label-cell">Kepala Stasiun</td>
                <td class="value-cell">
                    <span class="status-badge status-'.strtolower($this->cuti->status_kepala).'">
                        '. ($this->cuti->status_kepala == 'pending' ? 'MENUNGGU PERSETUJUAN' : e($statusLabels[$this->cuti->status_kepala] ?? strtoupper($this->cuti->status_kepala))) .'
                    </span>
                    '.($this->cuti->tanggapan_kepala ? '<br><em style="color: #6b7280; font-size: 9pt;">Catatan: '.e($this->cuti->tanggapan_kepala).'</em>' : '').'
                </td>
            </tr>
        </table>

        <div class="signature-section">
            <table class="signature-table">
                <tr>
                    <td width="45%" style="text-align: center; padding-left: 10px;">
                        <div class="date-row text-right"></div>
                        <div class="signature-title">Mengetahui,</div>
                        <div class="signature-role">Kasubbag Tata Usaha</div>
                        <div class="signature-box">'.$tataUsahaSignatureHtml.'</div>
                        <div class="signature-name">'.e($tataUsahaNama).'</div>
                        <div class="signature-nip">NIP. '.e($tataUsahaNip).'</div>
                    </td>
                    <td width="10%"></td>
                    <td width="45%" style="text-align: center; padding-right: 10px;">
                        <div class="date-row text-right">
                            Yogyakarta, '.Carbon::now()->isoFormat('D MMMM Y').'
                        </div>
                        <div class="signature-title">Menyetujui,</div>
                        <div class="signature-role">Kepala Stasiun TVRI Yogyakarta</div>
                        <div class="signature-box">'.$kepalaSignatureHtml.'</div>
                        <div class="signature-name">'.e($kepalaNama).'</div>
                        <div class="signature-nip">NIP. '.e($kepalaNip).'</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="important-note" style="text-align: center; margin-top: 25px;">
            <strong>Catatan Penting:</strong> Dokumen ini merupakan salinan resmi yang dicetak dari Sistem E-Cuti TVRI Yogyakarta. Dicetak secara elektronik pada '.Carbon::now()->isoFormat('dddd, D MMMM Y [pukul] HH:mm').' WIB
        </div>
        ';

        $this->writeHTML($html, true, false, true, false, '');
    }
}