<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('users.index', [
            'users' => User::query()
                ->with(['roles', 'activeManagedLocations'])
                ->when($request->search, fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('login_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }))
                ->when($request->role, fn ($query, $role) => $query->role($role))
                ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'roles' => Role::orderBy('name')->get(),
            'totalUsers' => User::count(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $user = User::create([
            'name' => $data['name'],
            'login_code' => strtoupper(trim($data['login_code'])),
            'email' => ($data['email'] ?? null) ?: strtolower(trim($data['login_code'])).'@login.invalid',
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'active' => $request->boolean('active', true),
            'email_verified_at' => now(),
        ]);
        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('users.index')->with('status', 'Utilizatorul a fost creat.');
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);
        if ($user->is($request->user()) && ! $request->boolean('active')) {
            throw ValidationException::withMessages(['active' => 'Nu iti poti dezactiva propriul cont.']);
        }

        $updates = [
            'name' => $data['name'],
            'login_code' => strtoupper(trim($data['login_code'])),
            'email' => ($data['email'] ?? null) ?: strtolower(trim($data['login_code'])).'@login.invalid',
            'phone' => $data['phone'] ?? null,
            'active' => $request->boolean('active'),
        ];
        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        $user->update($updates);
        $user->syncRoles($data['roles'] ?? []);

        return redirect()->route('users.index')->with('status', 'Utilizatorul a fost actualizat.');
    }

    private function formData(?User $user = null): array
    {
        return [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ];
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $request->merge([
            'login_code' => Str::upper(trim((string) $request->input('login_code'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'login_code' => ['required', 'string', 'max:40', Rule::unique('users', 'login_code')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
