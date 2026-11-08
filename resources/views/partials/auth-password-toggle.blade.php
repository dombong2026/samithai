{{-- Shared accessible password visibility control for authentication fields. --}}
<button type="button" class="eye" data-password-toggle="{{ $target }}" aria-controls="{{ $target }}" aria-label="Show password" aria-pressed="false" title="Show password">
    <svg class="eye-icon-show" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0Z"/>
        <circle cx="12" cy="12" r="3"/>
    </svg>
    <svg class="eye-icon-hide" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.7 5.1A10.8 10.8 0 0 1 21.94 11.65a1 1 0 0 1 0 .7 11 11 0 0 1-1.5 2.5"/>
        <path d="M14.1 14.1a3 3 0 0 1-4.2-4.2"/>
        <path d="M17.5 17.5A10.75 10.75 0 0 1 2.06 12.35a1 1 0 0 1 0-.7 10.8 10.8 0 0 1 4.45-5.15"/>
        <path d="m2 2 20 20"/>
    </svg>
</button>
