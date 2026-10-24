@extends('admin.layouts.master')
@section('content')
<style>
.lp-toolbar{position:sticky;top:0;z-index:1020;background:#fff;border:1px solid #dce5f2;border-radius:10px;padding:12px 16px;box-shadow:0 5px 18px #13284a12;margin-bottom:16px}
.lp-panel{background:#fff;border:1px solid #e1e8f1;border-radius:12px;padding:22px;margin-bottom:18px}
.lp-heading{font-weight:750;font-size:17px;color:#172c4c;margin-bottom:15px}
.lp-nav{display:flex;gap:8px;overflow-x:auto;padding:4px 1px 14px}
.lp-nav button{border:1px solid #dce5f2;background:#fff;color:#344767;padding:10px 19px;white-space:nowrap;border-radius:9px;font-weight:700}
.lp-nav button.active{background:#1958d5;color:white;border-color:#1958d5}
.lp-tab[hidden]{display:none!important}
.lp-theme{display:block;border:2px solid #e2e8f0;border-radius:10px;padding:13px;cursor:pointer;height:100%}
.lp-theme:has(input:checked){border-color:#2563eb;box-shadow:0 0 0 3px #dfeafe}
.lp-theme-color{height:70px;border-radius:7px;margin-bottom:9px}
.lp-media{background:#f7f9fc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;height:100%}
.lp-media img{width:100%;max-height:155px;object-fit:cover;border-radius:6px;margin-bottom:8px}
.lp-project{border:1px solid #dae3ef;border-radius:9px;margin-bottom:10px;background:#fff}
.lp-project summary{cursor:pointer;padding:14px;font-weight:700;color:#173054}
.lp-project[open] summary{border-bottom:1px solid #e5ebf3}
.lp-project-body{padding:16px}
.lp-row{display:flex;align-items:center;gap:12px;padding:10px 13px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:7px;background:#fff}
.lp-grip{cursor:grab;color:#8794aa;font-size:20px;user-select:none}
.lp-row.dragging{opacity:.48}
.lp-muted{font-size:12px;color:#7a879a}
.lp-actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center}
@media(max-width:767px){.lp-panel{padding:15px}.lp-toolbar{top:0}.lp-actions .btn{margin-bottom:4px}.lp-heading{font-size:16px}}
</style>
<div class="content-wrapper">
 <div class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">
  <div><h1 class="m-0">Landing Page Manager</h1><p class="text-muted mb-0">Design, content and publishing — all in one organized workspace</p></div>
  <a href="{{ route('index') }}" target="_blank" rel="noopener" class="btn btn-outline-primary">View Public Website ↗</a>
 </div></div>
 <section class="content"><div class="container-fluid">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Please review:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form id="publish-landing" method="POST" action="{{ route('landing-page.admin.publish') }}">@csrf</form>
  <form id="landing-editor" method="POST" action="{{ route('landing-page.admin.update') }}">@csrf @method('PUT')</form>
  <div class="lp-toolbar d-flex align-items-center justify-content-between flex-wrap">
   <div><strong>Landing Page Editor</strong><div><span class="badge badge-info">Theme: {{ ucfirst($page->theme ?: 'corporate') }}</span>
   <span class="badge {{ $hasDraft?'badge-warning':'badge-success' }}">{{ $hasDraft?'Unpublished draft':'Published / no draft' }}</span></div></div>
   <div class="lp-actions">
    @can('update landing page')<button type="submit" form="landing-editor" class="btn btn-primary">Save Draft</button>@endcan
    <a href="{{ route('landing-page.admin.preview') }}" target="_blank" rel="noopener" class="btn btn-outline-info">Preview Draft ↗</a>
    @can('update landing page')<button type="submit" form="publish-landing" class="btn btn-success" onclick="return confirm('Publish the saved draft? Unsaved changes are not included.')">Publish Saved Draft</button>@endcan
   </div>
  </div>
  <nav class="lp-nav" aria-label="Landing editor sections" role="tablist">
   @foreach(['design'=>'Design & Content','projects'=>'Projects','contact'=>'Contact','seo'=>'SEO','sections'=>'Sections & Order'] as $key => $label)
   <button type="button" id="lp-button-{{ $key }}" role="tab" data-tab="{{ $key }}" aria-controls="lp-tab-{{ $key }}" aria-selected="{{ $key==='design'?'true':'false' }}" class="{{ $key==='design'?'active':'' }}">{{ $label }}</button>
   @endforeach
  </nav>
  <div id="lp-tab-design" class="lp-tab" role="tabpanel" aria-labelledby="lp-button-design">
   <div class="lp-panel"><div class="lp-heading">Choose a design</div><div class="row">
    @foreach(['corporate'=>['Premium Corporate','Navy, white and gold','linear-gradient(125deg,#101d38 52%,#fff 52%)'],'luxury'=>['Luxury Real Estate','Cinematic dark and gold','linear-gradient(125deg,#080c13 52%,#af8751 52%)'],'vibrant'=>['Modern & Vibrant','Blue gradients and energy','linear-gradient(125deg,#1555ed 52%,#60c6fb 52%)']] as $key=>$theme)
    <div class="col-md-4 mb-3"><label class="lp-theme"><div class="lp-theme-color" style="background:{{ $theme[2] }}"></div>
     <input form="landing-editor" type="radio" name="theme" value="{{ $key }}" {{ old('theme',$page->theme ?: 'corporate')===$key?'checked':'' }}>
     <strong>{{ $theme[0] }}</strong><div class="lp-muted">{{ $theme[1] }}</div>
    </label></div>@endforeach
   </div></div>
   @foreach([
     'Hero'=>['hero_eyebrow','hero_title','hero_description','hero_image'],
     'About us'=>['about_title','about_description','about_image'],
     'More content'=>['services_title','projects_title','cta_title','contact_title','footer_description']
   ] as $group=>$fields)
   <div class="lp-panel"><div class="lp-heading">{{ $group }}</div>
    <div class="row">
    <div class="{{ $group==='More content'?'col-12':'col-lg-7' }}"><div class="row">
     @foreach($fields as $key)
      @continue(in_array($key,['hero_image','about_image']))
      <div class="{{ in_array($key,['hero_description','about_description','footer_description'])?'col-12':'col-md-6' }} form-group">
      <label for="lp-{{ $key }}">{{ ucwords(str_replace('_',' ',$key)) }}</label>
      @if(in_array($key,['hero_description','about_description','footer_description']))
      <textarea form="landing-editor" class="form-control" id="lp-{{ $key }}" name="content[{{ $key }}]" maxlength="1200" rows="3">{{ old('content.'.$key,$page->contentValue($key)) }}</textarea>
      @else
      <input form="landing-editor" class="form-control" id="lp-{{ $key }}" name="content[{{ $key }}]" maxlength="1200" value="{{ old('content.'.$key,$page->contentValue($key)) }}">
      @endif
      </div>
     @endforeach
    </div></div>
    @if($group!=='More content')
    @php $slot=$group==='Hero'?'hero_image':'about_image'; @endphp
    <div class="col-lg-5">
      <div class="lp-media"><label for="lp-{{ $slot }}">{{ $group }} image</label>
       @if($page->contentValue($slot))<img src="{{ $page->contentValue($slot) }}" alt="Current {{ $group }} image" loading="lazy">@endif
       <input form="landing-editor" class="form-control mb-2" id="lp-{{ $slot }}" name="content[{{ $slot }}]" maxlength="2048" value="{{ old('content.'.$slot,$page->contentValue($slot)) }}" placeholder="Image URL or /storage/landing-page/...">
       <button type="button" class="btn btn-sm btn-outline-secondary mb-3 reset-image" data-target="lp-{{ $slot }}">Use default image</button>
       <form method="POST" action="{{ route('landing-page.admin.upload-image') }}" enctype="multipart/form-data" class="mt-2">@csrf
        <input type="hidden" name="slot" value="{{ $slot }}"><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="form-control-file mb-2">
        <button class="btn btn-sm btn-outline-primary" type="submit">Upload {{ $group }} image</button>
       </form>
       <div class="lp-muted mt-2">Image uploads save to draft separately. Save text changes first.</div>
      </div>
    </div>
    @endif
    </div>
   </div>
   @endforeach
  </div>
  <div id="lp-tab-projects" class="lp-tab" role="tabpanel" aria-labelledby="lp-button-projects" hidden>
   <div class="lp-panel"><div class="lp-heading">Project showcase</div>
    <div class="form-group"><label for="projects_mode">Content source</label>
      <select form="landing-editor" id="projects_mode" name="projects_mode" class="form-control">
       <option value="default" {{ old('projects_mode',$page->projects_mode ?: 'default')==='default'?'selected':'' }}>Default promotional cards</option>
       <option value="actual" {{ old('projects_mode',$page->projects_mode ?: 'default')==='actual'?'selected':'' }}>Selected actual CRM projects</option>
      </select>
    </div>
    <p class="lp-muted">CRM project records stay unchanged; these promotional values belong only to the landing page.</p>
    <div class="row">@foreach($projects as $project)
     <div class="col-lg-4 col-sm-6"><label class="border rounded p-2 d-block">
      <input form="landing-editor" type="checkbox" name="project_ids[]" value="{{ $project->id }}" {{ in_array($project->id,old('project_ids',$page->project_ids ?? []))?'checked':'' }}>
      <strong>{{ $project->project }}</strong><span class="d-block lp-muted">{{ $project->address }}</span>
     </label></div>
    @endforeach</div>
   </div>
   <div class="lp-panel"><div class="lp-heading">Customize project cards</div>
    @foreach($projects as $project)
    <details class="lp-project"><summary>{{ $project->project }} — Edit promotional card</summary>
     <div class="lp-project-body"><div class="row">
      <div class="col-lg-7"><div class="form-group"><label>Promotional title</label><input form="landing-editor" class="form-control" maxlength="150" name="project_overrides[{{ $project->id }}][title]" value="{{ old('project_overrides.'.$project->id.'.title',$page->projectOverride($project->id,'title')) }}"></div>
      <div class="form-group"><label>Description</label><textarea form="landing-editor" class="form-control" maxlength="600" rows="3" name="project_overrides[{{ $project->id }}][description]">{{ old('project_overrides.'.$project->id.'.description',$page->projectOverride($project->id,'description')) }}</textarea></div></div>
      <div class="col-lg-5"><div class="lp-media">
       @if($page->projectOverride($project->id,'image'))<img src="{{ $page->projectOverride($project->id,'image') }}" loading="lazy" alt="Project cover">@endif
       <label for="project-image-{{ $project->id }}">Cover image URL</label>
       <input form="landing-editor" id="project-image-{{ $project->id }}" class="form-control mb-2" maxlength="2048" name="project_overrides[{{ $project->id }}][image]" value="{{ old('project_overrides.'.$project->id.'.image',$page->projectOverride($project->id,'image')) }}">
       <button type="button" class="btn btn-sm btn-outline-secondary mb-2 reset-image" data-target="project-image-{{ $project->id }}">Use default artwork</button>
       <form method="POST" action="{{ route('landing-page.admin.upload-image') }}" enctype="multipart/form-data" class="mt-2">@csrf
       <input type="hidden" name="slot" value="project_image"><input type="hidden" name="project_id" value="{{ $project->id }}">
       <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="form-control-file mb-2">
       <button class="btn btn-sm btn-outline-primary" type="submit">Upload project cover</button></form>
      </div></div>
     </div></div>
    </details>
    @endforeach
   </div>
  </div>
  <div id="lp-tab-contact" class="lp-tab" role="tabpanel" aria-labelledby="lp-button-contact" hidden>
   <div class="lp-panel"><div class="lp-heading">Direct contact details</div><p class="text-muted">No visitor inquiry form or public database write. Contact links open WhatsApp, phone or email.</p>
    <form id="landing-contact-form" method="POST" action="{{ route('landing-page.admin.contact') }}">@csrf
     <div class="row">
      <div class="col-md-4 form-group"><label for="contact-phone">Phone number</label><input class="form-control" id="contact-phone" type="tel" maxlength="24" name="contact_phone" value="{{ old('contact_phone',$page->contentValue('contact_phone')) }}" placeholder="+923001234567"></div>
      <div class="col-md-4 form-group"><label for="contact-wa">WhatsApp number</label><input class="form-control" id="contact-wa" type="tel" maxlength="24" name="contact_whatsapp" value="{{ old('contact_whatsapp',$page->contentValue('contact_whatsapp')) }}" placeholder="+923001234567"></div>
      <div class="col-md-4 form-group"><label for="contact-email">Email</label><input class="form-control" id="contact-email" type="email" maxlength="255" name="contact_email" value="{{ old('contact_email',$page->contentValue('contact_email')) }}" placeholder="contact@example.com"></div>
     </div>
     @can('update landing page')<button type="submit" class="btn btn-primary">Save Contact Details</button>@endcan
    </form>
    <p class="lp-muted mt-3 mb-0">Contact settings are saved to draft separately; use Preview Draft and Publish when ready.</p>
   </div>
  </div>
  <div id="lp-tab-seo" class="lp-tab" role="tabpanel" aria-labelledby="lp-button-seo" hidden>
   <div class="lp-panel"><div class="lp-heading">Search engine settings</div>
    <div class="form-group"><label for="seo-title">Page title</label><input form="landing-editor" id="seo-title" name="content[seo_title]" class="form-control" maxlength="70" value="{{ old('content.seo_title',$page->contentValue('seo_title')) }}"></div>
    <div class="form-group"><label for="seo-desc">Meta description</label><textarea form="landing-editor" id="seo-desc" name="content[seo_description]" class="form-control" maxlength="200" rows="3">{{ old('content.seo_description',$page->contentValue('seo_description')) }}</textarea></div>
    <p class="lp-muted">Search engines may choose their own snippets. Save Draft before Preview.</p>
   </div>
  </div>
  <div id="lp-tab-sections" class="lp-tab" role="tabpanel" aria-labelledby="lp-button-sections" hidden>
   <div class="lp-panel"><div class="lp-heading">Section visibility & order</div><p class="lp-muted">Drag sections or use arrow controls. Save Draft to apply to Preview.</p>
    <div id="landing-section-list">
    @foreach($page->orderedSections() as $key)
     <div class="lp-row section-sort-row" draggable="true" data-section="{{ $key }}">
      <span class="lp-grip" title="Drag to reorder" aria-hidden="true">⠿</span><strong class="flex-grow-1">{{ \App\Models\LandingPage::SECTION_NAMES[$key] }}</strong>
      <input form="landing-editor" type="hidden" name="sections[{{ $key }}]" value="0">
      <label class="mb-0 mr-3"><input form="landing-editor" type="checkbox" name="sections[{{ $key }}]" value="1" {{ old('sections.'.$key,$page->sectionEnabled($key)?'1':'0')==='1'?'checked':'' }}> Visible</label>
      <input form="landing-editor" type="hidden" name="section_order[]" value="{{ $key }}">
      <button type="button" class="btn btn-sm btn-outline-secondary move-up" aria-label="Move section up">↑</button>
      <button type="button" class="btn btn-sm btn-outline-secondary move-down" aria-label="Move section down">↓</button>
     </div>
    @endforeach
    </div>
   </div>
  </div>
 </div></section>
</div>
@endsection
@section('js')
<script>
(function(){
 const tabs=document.querySelectorAll('.lp-nav [data-tab]');
 function activate(id){
  tabs.forEach(b=>{const active=b.dataset.tab===id;b.classList.toggle('active',active);b.setAttribute('aria-selected',active?'true':'false');document.getElementById('lp-tab-'+b.dataset.tab).hidden=!active});
  sessionStorage.setItem('landing-editor-tab',id);
 }
 tabs.forEach(b=>b.addEventListener('click',()=>activate(b.dataset.tab)));
 const initial=new URLSearchParams(location.search).get('tab') || sessionStorage.getItem('landing-editor-tab') || 'design';
 activate([...tabs].some(b=>b.dataset.tab===initial)?initial:'design');
 document.querySelectorAll('.reset-image').forEach(btn=>btn.addEventListener('click',()=>{const input=document.getElementById(btn.dataset.target);if(input){input.value='';input.dispatchEvent(new Event('change',{bubbles:true}))}}));
 const list=document.getElementById('landing-section-list');let dragging=null;
 if(list){
  list.addEventListener('click',e=>{
   const b=e.target.closest('button');if(!b)return;const row=b.closest('.section-sort-row');
   if(b.classList.contains('move-up')&&row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);
   if(b.classList.contains('move-down')&&row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);
  });
  list.addEventListener('dragstart',e=>{dragging=e.target.closest('.section-sort-row');if(dragging){dragging.classList.add('dragging');e.dataTransfer.effectAllowed='move'}});
  list.addEventListener('dragover',e=>{e.preventDefault();const row=e.target.closest('.section-sort-row');if(row&&dragging&&row!==dragging){const rect=row.getBoundingClientRect();list.insertBefore(dragging,e.clientY<rect.top+rect.height/2?row:row.nextSibling)}});
  list.addEventListener('dragend',()=>{if(dragging)dragging.classList.remove('dragging');dragging=null});
 }
})();
</script>
@endsection