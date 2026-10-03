@props([
    'code' => '',
    'meta' => null,
    'copy' => false,
    'maxHeight' => 0,
])

<div {{ $attributes->class(['ee-code']) }} x-data>
    @if(null !== $meta || $copy)
        <div class="ee-code__head">
            <span class="ee-code__meta">{{ $meta }}</span>
            @if($copy)
                <button
                    type="button"
                    class="ee-code__copy"
                    aria-label="Copy to clipboard"
                    @click="window.navigator.clipboard?.writeText($refs.code.textContent)"
                >Copy</button>
            @endif
        </div>
    @endif
    <pre class="ee-code__body" x-ref="code" @if($maxHeight > 0) style="--ee-code-max-height: {{ max(0, (int) $maxHeight) }}px" @endif>{{ $code }}</pre>
</div>
