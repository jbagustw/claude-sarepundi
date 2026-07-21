<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'role' => ['sometimes', Rule::in(['user', 'mitra', 'admin'])],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'search' => ['sometimes', 'string', 'max:255'],
        ]);

        $users = User::with('mitraProfile')
            ->when($request->filled('role'), fn ($q) => $q->role($request->query('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->get();

        return AdminUserResource::collection($users);
    }

    public function suspend(User $user)
    {
        abort_if($user->hasRole('admin'), 422, 'Akun admin tidak dapat disuspend.');

        $user->update(['status' => 'suspended']);

        return new AdminUserResource($user->load('mitraProfile'));
    }

    public function activate(User $user)
    {
        $user->update(['status' => 'active']);

        return new AdminUserResource($user->load('mitraProfile'));
    }
}
