<details class="more" data-close-outside style="position:relative"><summary class="btn btn-sm">🖨 Cetak Surat ▾</summary>
    <div class="card" style="position:absolute;right:0;z-index:20;min-width:270px;padding:6px;margin-top:4px;display:grid;gap:2px;text-align:left">
        @foreach(\App\Support\LetterForms::TITLES as $j => $t)<a href="{{ route('surat', [$j, $s]) }}" target="_blank">{{ $t }}</a>@endforeach
    </div>
</details>
