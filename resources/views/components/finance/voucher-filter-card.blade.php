{{-- Shared voucher filter panel. Each voucher supplies its own scoped form/inputs;
     styling and surrounding card stay centralized for all voucher families. --}}
<div {{ $attributes->class(['card', 'bm-card', 'bm-filter', 'bm-voucher-filter-card']) }}>
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
