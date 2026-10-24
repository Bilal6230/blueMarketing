@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <div class="content-header"><div class="container-fluid"><div class="d-flex justify-content-between align-items-center flex-wrap">
      <div><h1 class="m-0">Landing Page Manager</h1><p class="text-muted mb-0">Edit privately, preview your draft, then publish changes</p></div>
      <a href="{{ route('index') }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Open Landing Page ↗</a>
    </div></div></div>
    <section class="content"><div class="container-fluid">
      @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
      @if($errors->any())<div class="alert alert-danger"><strong>Check your changes:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
      <div class="card border-info"><div class="card-body d-flex flex-wrap justify-content-between align-items-center">
        <div><strong>Publishing workflow</strong><p class="text-muted mb-0">Save Draft → Preview Draft → Publish. Public visitors see the last published version.</p></div>
        <div class="mt-2"><a class="btn btn-outline-info mr-2" href="{{ route('landing-page.admin.preview') }}" target="_blank" rel="noopener">Preview Draft ↗</a>
        @can('update landing page')<button type="submit" form="publish-landing" class="btn btn-success" onclick="return confirm('Publish your saved draft to the public website?')">Publish Saved Draft</button>@endcan</div>
      </div></div>
      <form id="publish-landing" method="POST" action="{{ route('landing-page.admin.publish') }}">@csrf</form>
      <form id="landing-editor" method="POST" action="{{ route('landing-page.admin.update') }}">@csrf @method('PUT')
        <div class="card"><div class="card-header"><h3 class="card-title">1. Active design</h3></div><div class="card-body"><div class="row">
        @foreach(['corporate'=>['Premium Corporate','Navy, white and gold','linear-gradient(125deg,#101d38 50%,#fff 50%)'],'luxury'=>['Luxury Real Estate','Dark cinematic experience','linear-gradient(125deg,#080c13 50%,#af8751 50%)'],'vibrant'=>['Modern & Vibrant','Royal blue, gradients and energy','linear-gradient(125deg,#0e56ee 50%,#68cafa 50%)']] as $key => $theme)
          <div class="col-md-4 mb-3"><label class="card h-100 p-3" style="cursor:pointer;border:2px solid #dae2ee"><div style="height:85px;border-radius:6px;background:{{ $theme[2] }};margin-bottom:14px"></div><div><input type="radio" name="theme" value="{{ $key }}" {{ old('theme',$page->theme ?: 'corporate') === $key ? 'checked':'' }}> <strong>{{ $theme[0] }}</strong></div><small class="text-muted">{{ $theme[1] }}</small></label></div>
        @endforeach
        </div></div></div>
        <div class="card"><div class="card-header"><h3 class="card-title">2. Customize website sections</h3></div><div class="card-body"><p class="text-muted">Leave a text field blank to restore its original promotional copy.</p><div class="row">
        @foreach(\App\Models\LandingPage::DEFAULT_CONTENT as $key => $fallback)
        <div class="{{ in_array($key,['hero_description','about_description','footer_description']) ? 'col-12':'col-md-6' }}"><div class="form-group"><label for="field-{{ $key }}">{{ ucwords(str_replace('_',' ',$key)) }}</label>
          @if(in_array($key,['hero_description','about_description','footer_description']))
          <textarea class="form-control" id="field-{{ $key }}" rows="3" name="content[{{ $key }}]">{{ old('content.'.$key,$page->contentValue($key)) }}</textarea>
          @else
          <input class="form-control" id="field-{{ $key }}" name="content[{{ $key }}]" value="{{ old('content.'.$key,$page->contentValue($key)) }}" placeholder="{{ $fallback }}" maxlength="{{ in_array($key,['hero_image','about_image']) ? 2048:1200 }}">
          @endif
        </div></div>
        @endforeach
        </div><hr><h5>Show or hide sections</h5><div class="row">
        @foreach(\App\Models\LandingPage::SECTION_NAMES as $key => $label)
        <div class="col-md-4"><input type="hidden" name="sections[{{ $key }}]" value="0"><label><input type="checkbox" name="sections[{{ $key }}]" value="1" {{ old('sections.'.$key,$page->sectionEnabled($key) ? '1':'0') === '1' ? 'checked':'' }}> {{ $label }}</label></div>
        @endforeach
        </div></div></div>
        <div class="card"><div class="card-header"><h3 class="card-title">3. Project showcase</h3></div><div class="card-body">
        <div class="form-group"><label for="projects_mode">Content source</label><select class="form-control" id="projects_mode" name="projects_mode">
        <option value="default" {{ old('projects_mode',$page->projects_mode ?: 'default') === 'default'?'selected':'' }}>Default promotional cards</option>
        <option value="actual" {{ old('projects_mode',$page->projects_mode ?: 'default') === 'actual'?'selected':'' }}>Selected actual projects</option></select></div>
        <p class="text-muted">Choose project records below. Project names and addresses will be read from existing CRM project records; no CRM records will be edited.</p>
        <div class="row">@foreach($projects as $project)<div class="col-md-4 mb-2"><label class="border rounded p-2 d-block"><input type="checkbox" name="project_ids[]" value="{{ $project->id }}" {{ in_array($project->id,old('project_ids',$page->project_ids ?? [])) ? 'checked':'' }}> <strong>{{ $project->project }}</strong><small class="d-block text-muted">{{ $project->address }}</small></label></div>@endforeach</div>
        @if($projects->isEmpty())<p>No projects found. You can keep the default promotional cards.</p>@endif
        </div></div>
        <div class="mb-4 d-flex align-items-center"><button type="submit" class="btn btn-primary btn-lg mr-3">Save Draft</button><span class="text-muted">Saving does not change the live website. Preview and publish when ready.</span></div>
      </form>
      <div class="card"><div class="card-header"><h3 class="card-title">4. Image Manager</h3></div>
      <div class="card-body"><p class="text-muted">Upload JPG, PNG or WebP images up to 4 MB. Uploads apply to the draft. Save text edits before uploading.</p>
      <div class="row">
      @foreach(['hero_image'=>'Hero image', 'about_image'=>'About image'] as $slot => $label)
       <div class="col-md-6"><form action="{{ route('landing-page.admin.upload-image') }}" method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-3">
       @csrf <input type="hidden" name="slot" value="{{ $slot }}">
       <label for="upload-{{ $slot }}">{{ $label }}</label>
       @if($page->contentValue($slot))<div class="mb-2"><img src="{{ $page->contentValue($slot) }}" alt="Current {{ $label }} preview" style="max-width:100%;height:160px;object-fit:cover;border-radius:7px"></div>@endif
       <input id="upload-{{ $slot }}" type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="form-control-file mb-3">
       <button class="btn btn-outline-primary" type="submit">Upload to Draft</button>
       </form></div>
      @endforeach
      </div></div></div>
    </div></section>
</div>
@endsection
