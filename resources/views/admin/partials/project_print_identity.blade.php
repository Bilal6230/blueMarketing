{{-- Reusable print identity: pass the document's project ID, not another selected project. --}}
@php($printProjectIdentity = \App\Support\ProjectBranding::project($brandingProjectId ?? null))
<div class="bm-print-project-identity" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;page-break-inside:avoid;break-inside:avoid">
    <img src="{{ \App\Support\ProjectBranding::logoUrl($printProjectIdentity) }}"
         alt="{{ \App\Support\ProjectBranding::title($printProjectIdentity) }} logo"
         style="width:54px;height:54px;object-fit:contain;flex-shrink:0">
    <strong style="font-size:17px;line-height:1.2">{{ \App\Support\ProjectBranding::title($printProjectIdentity) }}</strong>
</div>
