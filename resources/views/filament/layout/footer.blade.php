<style>
    /* Styling Footer Manual agar tidak tergantung Tailwind Filament */
    .tvri-footer {
        width: 100%;
        padding: 1.5rem; /* Setara p-6 */
        background-color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-family: 'Inter', sans-serif; /* Font standar Filament */
        box-sizing: border-box;
        /* Ubah margin-top dari 3rem menjadi 2rem atau auto */
        margin-bottom: 2rem; 
        
        /* Pastikan width auto agar mengikuti container, bukan memaksa 100% viewport */
        width: 100%; 
        
        /* Tambahkan rounded agar manis (opsional) */
        border-radius: 0.5rem;
    }
    
    .tvri-text {
        font-size: 0.875rem; /* Setara text-sm */
        color: #6b7280; /* Abu-abu */
        line-height: 1.25rem;
    }

    .tvri-link {
        color: #2563eb; /* Biru TVRI */
        text-decoration: none;
        font-weight: 600;
        transition: color 0.2s;
    }
    
    .tvri-link:hover {
        text-decoration: underline;
        color: #1d4ed8;
    }

    .tvri-menu {
        list-style: none;
        display: flex;
        gap: 1.5rem; /* Jarak antar menu */
        margin: 0;
        padding: 0;
    }

    /* Responsif untuk HP (Layar kecil) */
    @media (max-width: 768px) {
        .tvri-footer {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        .tvri-menu {
            justify-content: center;
            font-size: 0.8rem;
        }
    }

    /* Dukungan Dark Mode (Otomatis ikut tema Filament) */
    .fi-theme-dark .tvri-footer, 
    html.dark .tvri-footer {
        background-color: #18181b; /* Gelap */
        border-top: 1px solid #27272a;
    }
    .fi-theme-dark .tvri-text,
    html.dark .tvri-text {
        color: #a1a1aa; /* Teks terang */
    }
</style>

<footer class="tvri-footer">
    <span class="tvri-text">
        &copy; {{ date('Y') }} 
        <a href="https://yogyakarta.tvri.go.id/" target="_blank" class="tvri-link">
            TVRI Stasiun D.I. Yogyakarta
        </a>&lt;/&gt;
        All Rights Reserved.
    </span>

    <ul class="tvri-menu tvri-text">
        <li>
            <span style="font-weight: 500;">Sistem E-Cuti v1.0</span>
        </li>
        <li style="opacity: 0.3;">|</li>
        <li>
            <a href="#" class="tvri-link">Bantuan</a>
        </li>
    </ul>
</footer>