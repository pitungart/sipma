{{-- Ringkasan sebagai chip angka: angka tebal + label, sejajar di bawah judul halaman --}}
<p class="sipma-summary">
    @foreach ($items as $item)
        <span class="sipma-summary-chip"><strong>{{ $item['value'] }}</strong> {{ $item['label'] }}</span>
    @endforeach
</p>
