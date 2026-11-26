@extends('admin.layouts.master')

@push('style')
<style>
.bm-my-profile .profile-workspace{border:1.5px solid #b4c6d8;border-top:3px solid #2563eb;border-radius:10px;background:#fff;overflow:hidden;max-width:1100px;margin:auto}
.bm-my-profile .profile-head{padding:16px 20px;background:#f4f8fc;border-bottom:1px solid #bed0e0;display:flex;align-items:center;gap:15px}
.bm-my-profile .profile-photo{width:78px;height:78px;object-fit:cover;border-radius:50%;border:2px solid #bfd0e0;background:#eef4fb}
.bm-my-profile .profile-placeholder{display:flex;align-items:center;justify-content:center;color:#3976bc;font-size:30px}
.bm-my-profile .profile-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding:20px}
.bm-my-profile .profile-fields .full{grid-column:1/-1}
.bm-my-profile .profile-fields label{font-weight:650;color:#244a70;font-size:14px;margin-bottom:6px}
.bm-my-profile .profile-fields .form-control{min-height:39px;border-color:#b8cadb;font-size:14px}
.bm-my-profile .profile-section{grid-column:1/-1;margin:7px 0 0;padding:12px 0 0;border-top:1px solid #cddbe7}
.bm-my-profile .profile-section h5{font-size:16px;font-weight:750;color:#174979;margin:0}
.bm-my-profile .profile-password-row{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.bm-my-profile .profile-password-row>div{min-width:0}
.bm-my-profile .profile-actions{display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;background:#f9fbfd;border-top:1px solid #cedce9}
.bm-my-profile .profile-help{font-size:12px;color:#6a819a}
.bm-my-profile .profile-error{color:#b42318;font-size:12px;margin-top:4px;display:none}
.bm-my-profile .profile-preview{width:95px;height:95px;border:2px dashed #a9bdd3;border-radius:10px;object-fit:cover;max-width:100%}
.bm-my-profile .profile-image-box{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
@media(max-width:850px){.bm-my-profile .profile-password-row{grid-template-columns:1fr}}@media(max-width:650px){.bm-my-profile .profile-fields{grid-template-columns:1fr;padding:14px}.bm-my-profile .profile-fields .full,.bm-my-profile .profile-section{grid-column:auto}}
</style>
@endpush

@section('content')
<div class="content-wrapper bm-my-profile">
 <div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">My Profile</h1></div><div class="col-sm-6"><ol class="breadcrumb float-sm-right"><li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li><li class="breadcrumb-item active">My Profile</li></ol></div></div></div></div>
 <section class="content"><div class="container-fluid">
  <div class="profile-workspace">
   <header class="profile-head">
    @if($user->avatar)
     <img id="my-profile-header-avatar" class="profile-photo" src="{{ asset('storage/'.ltrim($user->avatar,'/')) }}" alt="Your profile picture">
    @else
     <span id="my-profile-header-placeholder" class="profile-photo profile-placeholder"><i class="fas fa-user"></i></span>
    @endif
    <div><h4 id="my-profile-current-name" class="mb-1">{{ $user->name }}</h4><div class="text-muted">{{ $user->email }}</div><small class="profile-help">You can update your own contact details, photo and password.</small></div>
   </header>
   <form id="my-profile-form" method="POST" action="{{ route('my-profile.update') }}" enctype="multipart/form-data" data-ajax="off" novalidate>
    @csrf
    <div class="profile-fields">
     <div><label for="my-profile-name">Full Name *</label><input id="my-profile-name" type="text" class="form-control" name="name" value="{{ $user->name }}" maxlength="255" required><div class="profile-error" data-error-for="name"></div></div>
     <div><label for="my-profile-gender">Gender *</label><select id="my-profile-gender" class="form-control" name="gender" required><option value="">Select gender</option><option value="male" @selected($user->gender==='male')>Male</option><option value="female" @selected($user->gender==='female')>Female</option><option value="other" @selected($user->gender==='other')>Other</option></select><div class="profile-error" data-error-for="gender"></div></div>
     <div><label for="my-profile-phone">Phone *</label><input id="my-profile-phone" type="tel" class="form-control" name="phone_number" value="{{ $user->phone_number }}" maxlength="30" required autocomplete="tel"><div class="profile-error" data-error-for="phone_number"></div></div>
     <div><label>Email (read-only)</label><input type="email" class="form-control" value="{{ $user->email }}" disabled><small class="profile-help">Email, roles, project and account permissions cannot be changed here.</small></div>
     <div class="full"><label for="my-profile-avatar">Profile Image</label><div class="profile-image-box">
      <img id="my-profile-image-preview" class="profile-preview" src="{{ $user->avatar ? asset('storage/'.ltrim($user->avatar,'/')) : asset('images/default-avatar.png') }}" alt="Profile image preview">
      <div><input type="file" id="my-profile-avatar" class="form-control-file" name="avatar" accept="image/jpeg,image/png,image/webp"><small class="profile-help">JPG, PNG or WebP, up to 2 MB. Leave empty to keep the existing picture.</small><div class="profile-error" data-error-for="avatar"></div></div>
     </div></div>
     <div class="profile-section"><h5>Change Password (optional)</h5><small class="profile-help">Leave these fields blank to keep your current password.</small></div>
     <div class="profile-password-row">
      <div><label for="my-profile-current-password">Current Password</label><input id="my-profile-current-password" type="password" class="form-control" name="current_password" autocomplete="current-password"><div class="profile-error" data-error-for="current_password"></div></div>
      <div><label for="my-profile-password">New Password</label><input id="my-profile-password" type="password" class="form-control" name="password" autocomplete="new-password" minlength="8"><div class="profile-error" data-error-for="password"></div></div>
      <div><label for="my-profile-password-confirmation">Confirm New Password</label><input id="my-profile-password-confirmation" type="password" class="form-control" name="password_confirmation" autocomplete="new-password"></div>
     </div>
    </div>
    <div class="profile-actions"><button class="btn btn-primary" id="my-profile-save" type="submit"><i class="fas fa-save mr-1"></i>Save Changes</button></div>
   </form>
  </div>
 </div></section>
</div>
@endsection

@push('script')
<script src="{{ asset('js/my-profile.js') }}?v=1"></script>
@endpush
