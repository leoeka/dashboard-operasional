<style>
    body {
        font-family: 'Inter', sans-serif;
        background: #F4F6FB;
    }

    {{-- Diunduh dari CDN, jadi Alpine sempat render DOM sebelum x-cloak dipasang.
         Tanpa aturan ini overlay mobile dan dropdown akun berkedip saat muat. --}}
    [x-cloak] {
        display: none !important;
    }

    .nav-active {
        color: #25489c;
        background: #EEF4FF;
        font-weight: 600;
    }
</style>
