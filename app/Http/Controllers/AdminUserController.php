<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserStoreRequest;
use App\Http\Requests\AdminUserUpdateRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Utils\Fail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\JsonResponse;

class AdminUserController extends Controller
{
    private function validateAdmin(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->role) {
            abort(403, 'Akses ditolak.');
        }

        $permissions = $user->role->permissions;

        // LOGIKA KUNCI: Jika ada '*', dia adalah dewa, izinkan semua!
        if (is_array($permissions) && in_array('*', $permissions)) {
            return;
        }

        // Jika bukan superadmin, cek apakah dia punya akses spesifik ke manajemen user
        if (is_array($permissions) && in_array('Manajemen User', $permissions)) {
            return;
        }

        abort(403, 'Akses ditolak.');
    }

    /**
     * User can see users with role equal or lower than theirs
     */
    public function index(Request $request): JsonResource
    {
        $this->validateAdmin();

        $users = User::query()
            ->with('role')
            ->when($request->search, function ($query, $search): void {
                $query->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            })
            ->when($request->role_id, fn ($query, $roleId) => $query->where('role_id', $roleId))
            ->orderBy('full_name')
            ->paginate(50);

        return AdminUserResource::collection($users);
    }

    /**
     * User can't create another user with role higher than theirs through this endpoint.
     */
    public function store(AdminUserStoreRequest $request)
    {
        $this->validateAdmin();
        $validated = $request->validated();

        $user = new User();
        $user->username = $validated['username'];
        $user->full_name = $validated['full_name'];
        $user->password = bcrypt($validated['password']);
        $user->role_id = $validated['role_id'];
        // $user->application_type = 'admin';
        $user->is_active = true;
        $user->save();

        return AdminUserResource::make($user);
    }

    /**
     * User can't update their own role or password through this endpoint.
     */
    public function update(AdminUserUpdateRequest $request, User $user)
    {
        $this->validateAdmin();

        $validated = $request->validated();

        $user->username = $validated['username'];
        $user->full_name = $validated['full_name'];
        $user->role_id = $validated['role_id'];

        if (! empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        return AdminUserResource::make($user);
    }

    /**
     * User can't toggle their own account status.
     */
    public function toggleStatus(User $user): JsonResponse
    {
        $this->validateAdmin();

        Fail::group(function (Fail $fail) use ($user): void {
            $fail->if($user->id === auth()->id(), [
                'user' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        });

        $user->is_active = ! $user->is_active;
        $user->save();

        return response()->json([
            'id' => $user->id,
            'is_active' => $user->is_active,
            'message' => 'Status user berhasil diubah.',
        ]);
    }
}
