<div class="tab-pane settings-fill-tab {{ $voucherSettingsTabActive ? 'active' : '' }}" id="voucher-settings" data-settings-direct="1">
<style>
#voucher-settings{height:100%;min-height:0;overflow:hidden}
#voucher-settings .s4-shell{height:100%;min-height:0;display:flex;flex-direction:column;gap:10px}
#voucher-settings .s4-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
#voucher-settings .s4-heading h4{margin:0;font-size:19px;font-weight:750;color:#1a4c82}
#voucher-settings .s4-directory{border:1.5px solid #b9cbdc;border-top:3px solid #2563eb;border-radius:9px;overflow:hidden;display:flex;flex-direction:column;flex:1;min-height:0;background:white}
#voucher-settings .s4-scroll{min-height:0;flex:1;overflow:auto;overscroll-behavior:contain}
#voucher-settings .s4-table{width:100%;border-collapse:separate;border-spacing:0;white-space:nowrap;font-size:14px}
#voucher-settings .s4-table th{position:sticky;top:0;background:#eaf2fa;border:1px solid #bacbdb;border-bottom:2px solid #2563eb;padding:10px;z-index:2}
#voucher-settings .s4-table td{padding:10px;border-right:1px solid #c1d0dc;border-bottom:1px solid #c1d0dc;vertical-align:middle}
#voucher-settings .s4-table tbody tr:nth-child(even){background:#edf3f9}
#voucher-settings .s4-subtle{color:#627e97;font-size:12px}
#voucher-settings .s4-footer{padding:10px;border-top:1px solid #becfdf;color:#5f7488;font-size:12px}
#voucher-settings .s4-table .s4-action{min-width:95px;text-align:center}
#voucher-settings .bm-modern-export-toolbar{display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:9px;border-bottom:1px solid #becfdf;background:#fafcff}
#voucher-settings .bm-modern-export-btn{font-size:12px;border:1px solid #b7c9dc;color:#1a518a;border-radius:5px;background:white;padding:5px 8px}
#voucher-settings .bm-modern-column-menu{position:relative}
#voucher-settings .bm-modern-column-options{position:absolute;top:100%;left:0;background:white;border:1px solid #aebfd0;padding:8px;z-index:20;min-width:165px}
#voucher-settings .bm-modern-column-option{display:flex;gap:8px;align-items:center}
#voucher-settings .bm-account-col{float:right;border:0;background:transparent;color:#587596;padding:2px 6px;cursor:pointer}
#voucher-settings .bm-account-col.is-filtered{background:#d9eafb;border-radius:4px;color:#1661b8}
#voucher-settings .bm-account-footer{padding:8px 10px;border-top:1px solid #bdccdd;display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}
#voucher-settings .bm-account-footer-left{display:flex;align-items:center;gap:8px}
#voucher-settings .blue-actions-source{display:none!important}
#voucher-settings .blue-actions-cell{text-align:center}

#voucher-settings .s4-key{color:#6e8297;font-weight:600}
#s4-display-modal .s4-editor-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
#s4-display-modal label{font-weight:650}
#s4-display-modal .s4-full{grid-column:1/-1}
@media(max-width:700px){#s4-display-modal .s4-editor-grid{grid-template-columns:1fr}#s4-display-modal .s4-full{grid-column:auto}#voucher-settings .s4-table{min-width:650px}}
</style>
<div class="s4-shell" data-s4-status-url="{{ route('setting.voucher-availability.save') }}" data-s4-token="{{ csrf_token() }}">
 <header class="s4-heading">
  <div><h4>Voucher Display</h4><small class="text-muted">Voucher names, configured prefixes and receipt headers</small></div>
  <button type="button" id="s4-configure" class="btn btn-primary" @if(!$canManageFinanceSettings || !$voucherDisplaySettingsReady) disabled @endif><i class="fas fa-edit mr-1"></i> Configure Voucher Display</button>
 </header>
 @if(!$voucherDisplaySettingsReady)
  <div class="alert alert-warning">Voucher display settings migration is required.</div>
 @endif
 <section class="s4-directory settings-fill-card">
  <div class="s4-scroll settings-fill-scroll bm-account-table-wrap">
   <form id="settings-receipt-header-form" action="{{ route('setting.update') }}" method="POST" data-ajax="off">
    @csrf
    @method('PUT')
    @php($receiptHeaderSettings = \App\Models\Setting::where('type','toggle')->whereIn('key',array_map(fn($type) => $type.'_Receipt_Header',$types))->get()->keyBy('key'))
    @php($displayVoucherSeries = collect(\App\Services\Finance\ProjectVoucherDisplay::SERIES)->merge(array_keys(\App\Services\Finance\VoucherAvailability::FAMILIES))->merge($types)->unique()->reject(fn($series) => $series === 'CP')->values())
    <table class="s4-table" id="s4-directory-table">
     <thead><tr><th>Voucher Name</th><th>Current Prefix</th><th>Legacy Series</th><th>Print Header</th><th>Active / Inactive</th><th>Action</th></tr></thead>
     <tbody>
     @foreach($displayVoucherSeries as $series)
      @php($keyType = $series.'_Receipt_Header')
      @php($hasHeader = in_array($series, \App\Services\Finance\ProjectVoucherDisplay::SERIES, true) || array_key_exists($series, \App\Services\Finance\VoucherAvailability::FAMILIES) || $receiptHeaderSettings->has($keyType))
      @php($seriesActive = (int)($voucherAvailability[$series] ?? 1)===1)
      <tr data-s4-series="{{ $series }}">
       <td><strong>{{ \App\Services\Finance\ProjectVoucherDisplay::label($series) }}</strong></td>
       <td class="s4-current-prefix">{{ $voucherDisplaySettings[$series] ?? ($series==='CP' ? 'DR' : $series) }}</td>
       <td><span class="s4-key">{{ $series }}</span></td>
       <td>
        @if($hasHeader)
         <div class="custom-control custom-switch">
          <input type="hidden" name="toggles[{{ $keyType }}]" value="0">
          <input type="checkbox" class="custom-control-input settings-receipt-toggle" id="setting-receipt-{{ $series }}"
            name="toggles[{{ $keyType }}]" value="1" @checked((int)($receiptHeaderSettings->get($keyType)?->value ?? 0)===1)
            @cannot('update setting') disabled @endcannot>
          <label class="custom-control-label" for="setting-receipt-{{ $series }}">{{ (int)($receiptHeaderSettings->get($keyType)?->value ?? 0)===1 ? 'ON':'OFF' }}</label>
         </div>
        @else <span class="s4-subtle">Not configured</span> @endif
       </td>
       <td>
        @if(array_key_exists($series, \App\Services\Finance\VoucherAvailability::FAMILIES))
         <div class="custom-control custom-switch">
          <input type="checkbox" class="custom-control-input s4-availability-toggle" id="s4-active-{{ $series }}"
           data-s4-availability="{{ $series }}" value="1" @checked($seriesActive)
           @if(!$canManageFinanceSettings || !$voucherAvailabilityReady) disabled @endif>
          <label class="custom-control-label s4-availability-label" for="s4-active-{{ $series }}">{{ $seriesActive ? 'Active' : 'Inactive' }}</label>
         </div>
        @else <span class="s4-subtle">Legacy only</span> @endif
       </td>
       <td class="s4-action blue-actions-cell">
        @if(in_array($series, \App\Services\Finance\ProjectVoucherDisplay::SERIES, true))
         <button class="btn btn-outline-primary btn-sm s4-edit" type="button" data-s4-edit="{{ $series }}" @if(!$canManageFinanceSettings || !$voucherDisplaySettingsReady) disabled @endif><i class="fas fa-edit mr-1"></i>Edit</button>
        @else <span class="s4-subtle">Legacy header only</span> @endif
       </td>
      </tr>
     @endforeach
     </tbody>
    </table>
   </form>
  </div>
  <div class="bm-account-footer s4-list-footer"></div>
 </section>
</div>
<div class="modal fade" id="s4-display-modal" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Configure Voucher Display</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
  <form id="s4-display-form" method="POST" action="{{ route('setting.voucher-display.save') }}" data-ajax="off">
   @csrf
   <div class="modal-body">
    <div class="s4-editor-grid">
     <div class="s4-full"><label for="s4-series">Voucher Name *</label>
      <select id="s4-series" class="form-control" name="series" required>
       @foreach(\App\Services\Finance\ProjectVoucherDisplay::SERIES as $series)
        @continue($series === 'CP')
        <option value="{{ $series }}" data-prefix="{{ $voucherDisplaySettings[$series] ?? ($series==='CP' ? 'DR' : $series) }}">{{ \App\Services\Finance\ProjectVoucherDisplay::label($series) }}</option>
       @endforeach
      </select>
     </div>
     <div><label for="s4-prefix">Current Prefix *</label><input id="s4-prefix" class="form-control" name="prefix" maxlength="12" required></div>
     <div><label for="s4-start-number">Voucher Number (start)</label><input id="s4-start-number" class="form-control" placeholder="Pending safe numbering integration" type="number" min="1" disabled></div>
    </div>
    <div class="alert alert-info mt-3 mb-0 small">This batch saves prefix configuration only. Actual new voucher numbering, start numbers and previews remain disabled until all voucher writers and historical collision checks are integrated.</div>
   </div>
   <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Prefix Configuration</button></div>
  </form>
 </div></div>
</div>
</div>
<div class="modal fade" id="s4-prefix-confirm" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Save Prefix Configuration?</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
  <div class="modal-body">Save the selected voucher's prefix? Existing voucher numbers and posted transactions will not change.</div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="s4-prefix-confirm-yes">Yes, Save</button></div>
 </div></div>
</div>
@push('script')
<script src="{{ asset('js/settings-s4-voucher-display.js') }}?v=3"></script>
@endpush
