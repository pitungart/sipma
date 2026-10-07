{{-- Isi modal "Uji simulasi": nomor yang akan terbit berurutan menurut susunan yang sedang diedit --}}
@if (blank($rows))
    <p class="sipma-num-sim-empty">{{ __('numbering.incomplete') }}</p>
@else
    <ol class="sipma-num-sim">
        @foreach ($rows as $row)
            <li class="sipma-num-sim-row">
                <span class="sipma-num-sim-order">{{ __('numbering.simulation.order', ['n' => $loop->iteration]) }}</span>
                <span class="sipma-num-sim-number">{{ $row['number'] }}</span>
            </li>
        @endforeach
    </ol>
@endif
