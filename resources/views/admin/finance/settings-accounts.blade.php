<div class="tab-pane settings-fill-tab {{ $financeSettingsTabActive ? 'active' : '' }}" id="bank-accounts">
@if(!$financeSettingsSchemaReady || !$cashSettingsSchemaReady)
  <div class="alert alert-warning">Bank and cash account migrations are required. Run <code>php artisan migrate</code> before configuring accounts.</div>
@elseif(!$canManageFinanceSettings)
  <div class="alert alert-info">Select your authorized project to manage bank and cash accounts.</div>
@else
<style>
#bank-accounts{height:100%;min-height:0;overflow:hidden}
#bank-accounts .s3-shell{display:flex;flex-direction:column;height:100%;min-height:0;gap:12px}
#bank-accounts .s3-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
#bank-accounts .s3-toolbar h4{font-size:19px;font-weight:750;color:#1e4d79;margin:0}
#bank-accounts .s3-layout{display:grid;grid-template-columns:1fr 1fr;gap:14px;flex:1;min-height:0}
#bank-accounts .s3-card{border:1.5px solid #b0c5d9;border-top:3px solid #2563eb;border-radius:9px;display:flex;flex-direction:column;overflow:hidden;min-width:0;min-height:0;background:#fff}
#bank-accounts .s3-card-head{padding:11px 12px;background:#f3f8fc;border-bottom:1px solid #bccfdf;display:flex;align-items:center;justify-content:space-between}
#bank-accounts .s3-card-head h5{font-size:15px;font-weight:750;color:#214e7d;margin:0}
#bank-accounts .s3-table-scroll{min-height:0;overflow:auto;flex:1}
#bank-accounts .s3-table{width:100%;white-space:nowrap;border-collapse:separate;border-spacing:0;font-size:14px}
#bank-accounts .s3-table thead th{background:#eaf2fa;position:sticky;top:0;z-index:2;border:1px solid #b9c9db;border-bottom:2px solid #2563eb;padding:9px 8px}
#bank-accounts .s3-table tbody td{padding:10px 8px;border:1px solid #c3cfdb;vertical-align:middle}
#bank-accounts .s3-table tbody tr:nth-child(even){background:#edf3f9}
#bank-accounts .s3-table .s3-action{width:68px;text-align:center}
#bank-accounts .s3-note{font-size:12px;color:#65809a}
#s3-account-modal .s3-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
#s3-account-modal .s3-span{grid-column:1/-1}
#s3-account-modal .s3-form-grid label{font-weight:650;font-size:14px}
#bank-accounts .bm-modern-export-toolbar{display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:8px;border-bottom:1px solid #bbcddd}
#bank-accounts .bm-modern-export-btn{border:1px solid #b9cbe0;background:#fff;padding:5px 8px;border-radius:5px;color:#174879;font-size:12px;font-weight:650}
#bank-accounts .bm-account-col{float:right;border:0;background:transparent;color:#526b8b;cursor:pointer}
#bank-accounts .bm-account-footer{padding:8px;border-top:1px solid #b9cbe0;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px}
#bank-accounts .bm-account-footer-left{display:flex;align-items:center;gap:8px}
#bank-accounts .blue-actions-source{display:none!important}
#bank-accounts .blue-actions-cell{text-align:center}
@media(max-width:1100px){#bank-accounts{height:auto;overflow:visible}#bank-accounts .s3-shell{height:auto}#bank-accounts .s3-layout{grid-template-columns:1fr}#bank-accounts .s3-card{min-height:300px}#bank-accounts .s3-table-scroll{max-height:380px}}
@media(max-width:650px){#s3-account-modal .s3-form-grid{grid-template-columns:1fr}#s3-account-modal .s3-span{grid-column:auto}}
</style>
<div class="s3-shell" id="s3-account-workspace" data-detail="{{ route('setting.account.detail',['type'=>'bank','id'=>987654321]) }}" data-status="{{ route('setting.account.status',['type'=>'bank','id'=>987654321]) }}">
 <div class="s3-toolbar">
  <div><h4>Bank &amp; Cash Accounts</h4><small class="text-muted">Manage project-specific accounting profiles without changing historical postings</small></div>
  <div>
   <button class="btn btn-primary" type="button" data-s3-new="bank"><i class="fas fa-plus mr-1"></i> Configure Bank Account</button>
   <button class="btn btn-outline-primary" type="button" data-s3-new="cash"><i class="fas fa-plus mr-1"></i> Configure Cash Account</button>
  </div>
 </div>
 <div class="s3-layout">
  <section class="s3-card settings-fill-card">
   <header class="s3-card-head"><h5>Bank Accounts</h5><small class="text-muted" id="s3-bank-count">{{ $projectBankAccounts->count() }} accounts</small></header>
   <div class="bm-modern-export-toolbar bm-export-placeholder"><button class="bm-modern-export-btn" disabled>Column Visibility</button><button class="bm-modern-export-btn" disabled>Copy</button><button class="bm-modern-export-btn" disabled>CSV</button><button class="bm-modern-export-btn" disabled>Excel</button><button class="bm-modern-export-btn" disabled>PDF</button><button class="bm-modern-export-btn" disabled>Print</button></div>
   <div class="s3-table-scroll bm-account-table-wrap settings-fill-scroll">
    <table class="s3-table table-bordered" id="s3-bank-table"><thead><tr><th>Display Title</th><th>Bank / Account</th><th>COA Mapping</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @foreach($projectBankAccounts as $a)
     <tr data-id="{{ $a->id }}"><td>{{ $a->display_title ?: $a->account_title }}</td><td>{{ $a->bank_name }}<div class="s3-note">{{ $a->account_title }} · {{ $a->branch ?: 'No branch' }}</div></td><td>{{ $a->accountMapping->headAccounting->name ?? 'Head' }} / {{ $a->accountMapping->subheadAccounting->name ?? 'Account' }}</td><td><span class="badge badge-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span></td><td class="s3-action blue-actions-cell"><button type="button" class="btn btn-sm btn-primary s3-edit" data-id="{{ $a->id }}" data-type="bank">Edit</button><button type="button" class="btn btn-sm btn-{{ $a->is_active ? 'warning' : 'success' }} s3-status" data-id="{{ $a->id }}" data-type="bank" data-active="{{ $a->is_active ? 0 : 1 }}">{{ $a->is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
    @endforeach
    </tbody></table>
   </div>
  </section>
  <section class="s3-card settings-fill-card">
   <header class="s3-card-head"><h5>Cash Accounts</h5><small class="text-muted" id="s3-cash-count">{{ $projectCashAccounts->count() }} accounts</small></header>
   <div class="bm-modern-export-toolbar bm-export-placeholder"><button class="bm-modern-export-btn" disabled>Column Visibility</button><button class="bm-modern-export-btn" disabled>Copy</button><button class="bm-modern-export-btn" disabled>CSV</button><button class="bm-modern-export-btn" disabled>Excel</button><button class="bm-modern-export-btn" disabled>PDF</button><button class="bm-modern-export-btn" disabled>Print</button></div>
   <div class="s3-table-scroll bm-account-table-wrap settings-fill-scroll">
    <table class="s3-table table-bordered" id="s3-cash-table"><thead><tr><th>Display Title</th><th>COA Mapping</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @foreach($projectCashAccounts as $a)
     <tr data-id="{{ $a->id }}"><td>{{ $a->display_title }}</td><td>{{ $a->accountMapping->headAccounting->name ?? 'Head' }} / {{ $a->accountMapping->subheadAccounting->name ?? 'Account' }}</td><td><span class="badge badge-{{ $a->is_active ? 'success' : 'secondary' }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span></td><td class="s3-action blue-actions-cell"><button type="button" class="btn btn-sm btn-primary s3-edit" data-id="{{ $a->id }}" data-type="cash">Edit</button><button type="button" class="btn btn-sm btn-{{ $a->is_active ? 'warning' : 'success' }} s3-status" data-id="{{ $a->id }}" data-type="cash" data-active="{{ $a->is_active ? 0 : 1 }}">{{ $a->is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
    @endforeach
    </tbody></table>
   </div>
  </section>
 </div>
</div>
<div class="modal fade" id="s3-account-modal" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header bg-light"><h5 class="modal-title" id="s3-account-title">Configure Account</h5><button class="close" type="button" data-dismiss="modal">&times;</button></div>
  <form id="s3-account-form" method="POST" data-ajax="off" data-bank-url="{{ route('setting.bank-account.save') }}" data-cash-url="{{ route('setting.cash-account.save') }}">@csrf
  <input type="hidden" name="bank_account_id" disabled><input type="hidden" name="cash_account_id" disabled>
  <div class="modal-body"><div class="s3-form-grid">
   <div class="s3-span"><label>Display Title *</label><input class="form-control" name="display_title" maxlength="120" required placeholder="Title shown in voucher pickers"></div>
   <div class="s3-bank-only"><label>Bank Name *</label><input class="form-control" name="bank_name" maxlength="120" placeholder="e.g. Meezan Bank"></div>
   <div class="s3-bank-only"><label>Account Title *</label><input class="form-control" name="account_title" maxlength="120"></div>
   <div class="s3-bank-only"><label>Branch</label><input class="form-control" name="branch" maxlength="120"></div>
   <div class="s3-bank-only"><label>Account Number (optional)</label><input class="form-control" name="account_number" autocomplete="off" maxlength="255"><small class="s3-note" id="s3-bank-number-note">Encrypted. Leave blank on edit to preserve.</small></div>
   <div class="s3-span"><label>Project Chart of Accounts Mapping *</label><select name="account_mapping_id" class="form-control" required><option value="">Select active project COA mapping</option>@foreach($projectBankMappings as $mapping)<option value="{{ $mapping->id }}">{{ $mapping->headAccounting->name ?? 'Head' }} / {{ $mapping->subheadAccounting->name ?? 'Account' }}</option>@endforeach</select></div>
   <div><label>Status</label><select name="is_active" class="form-control"><option value="1">Active</option><option value="0">Inactive</option></select></div>
  </div><div class="text-muted mt-3 small">Existing financial entries and mapped account identities will not be rewritten.</div></div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Account</button></div>
  </form>
 </div></div>
</div>
@endif
</div>
@push('style')
<link rel="stylesheet" href="{{ asset('css/modern-account-directory.css') }}?v=2">
@endpush
@push('script')
<script src="{{ asset('js/settings-s3-accounts.js') }}?v=1"></script>
@endpush
