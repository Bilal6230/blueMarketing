@extends('admin.layouts.master')

@section('content')
<div class="content-wrapper">
    <div class="content-header"><div class="container-fluid"><div class="d-flex justify-content-between align-items-center flex-wrap">
      <div><h1 class="m-0">Landing Page Manager</h1><p class="text-muted mb-0">Themes, website copy, visibility and live project selection</p></div>
      <a href="{{ route('index') }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Open Landing Page ↗</a>
    </div></div></div>
    <section class="content"><div class="container-fluid">
      @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
      @if($errors->any())<div class="alert alert-danger"><strong>Check your changes:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
      <form method="POST" action="{{ route('landing-page.admin.update') }}">@csrf @method('PUT')
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
        <div class="mb-4 d-flex align-items-center"><button type="submit" class="btn btn-primary btn-lg mr-3">Save Landing Page Settings</button><span class="text-muted">Changes appear on the public homepage after saving.</span></div>
      </form>
    </div></section>
</div>
@endsection
