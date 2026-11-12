{{-- Shared voucher list container. Slots preserve module-specific table,
     toolbar, pagination and authorization/approval behavior. --}}
<div {{ $attributes->class(['card', 'bm-card', 'bm-voucher-table-card']) }}>
    {{ $slot }}
</div>
