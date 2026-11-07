@extends('layouts.workspace')
@section('title','Users & Roles')
@section('breadcrumb','Administration / Users')
@section('heading','Users & Roles')
@section('subtitle','Manage fixed customer, creator and administrator permissions.')
@section('toolbar')<a class="btn primary" href="{{ route('dashboard.users') }}">Refresh users</a>@endsection
@section('content')
<div class="notice">Customers can buy free prompts, creators can manage their own prompt and media libraries, and administrators can moderate and manage the platform. Role changes are audited; you cannot remove your own administrator role.</div>
<div class="stats" style="margin:19px 0"><div class="stat"><p>Administrator accounts</p><b>{{ $adminCount }}</b></div><div class="stat"><p>Matching users</p><b>{{ $users->total() }}</b></div></div>
<form method="get" class="filter-bar" action="{{ route('dashboard.users') }}"><label for="user-q" class="sr-only">Search users</label><input id="user-q" name="q" placeholder="Search name or email" value="{{ request('q') }}">
<label for="user-role" class="sr-only">Role</label><select id="user-role" name="role"><option value="">All roles</option>
@foreach(['customer','creator','admin'] as $role)<option value="{{ $role }}" @selected(request('role')===$role)>{{ ucfirst($role) }}</option>@endforeach</select>
<button class="btn primary" type="submit">Search & filter</button><a class="btn" href="{{ route('dashboard.users') }}">Clear</a></form>
<div class="table-scroll"><table><thead><tr><th>No.</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Action</th></tr></thead><tbody>
@forelse($users as $user)
<tr><td>{{ $users->firstItem()+$loop->index }}</td><td class="title-cell">{{ $user->name }}</td><td>{{ $user->email }}</td><td><span class="pill {{ $user->role }}">{{ ucfirst($user->role) }}</span></td><td>{{ $user->created_at->format('d M Y') }}</td>
<td><button class="icon-btn" type="button" data-action-toggle="user-{{ $user->id }}" aria-label="Actions for {{ $user->name }}">⋮</button>
<template id="user-{{ $user->id }}">
<button type="button" data-modal-open="role-modal" data-resource-url="{{ route('dashboard.users.role',$user) }}" data-field-name="{{ $user->name }}" data-field-role="{{ $user->role }}">Change role</button>
</template></td></tr>
@empty<tr><td colspan="6" class="empty">No matching accounts.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$users])
<dialog id="role-modal"><div class="modal-head"><h2>Change account role</h2><button class="icon-btn" type="button" data-modal-close aria-label="Close">✕</button></div>
<form method="post" action="{{ route('dashboard.users') }}" data-resource-form>@csrf<input type="hidden" name="_method" value="PUT">
<div class="modal-content stack">
<div class="field-group"><label for="role-user-name">User</label><input class="field" id="role-user-name" data-fill="name" type="text" readonly></div>
<div class="field-group"><label for="role-select">Access role</label><select class="field" id="role-select" name="role" data-fill="role"><option value="customer">Customer</option><option value="creator">Creator</option><option value="admin">Administrator</option></select></div>
<div class="notice warning">Administrator access permits all platform management functions. Only assign this role to a fully trusted account.</div>
</div><div class="modal-foot"><button class="btn" type="button" data-modal-close>Cancel</button><button class="btn primary" type="submit">Update role</button></div></form></dialog>
@endsection
