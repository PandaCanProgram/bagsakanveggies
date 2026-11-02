<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaserRequest;
use App\Http\Requests\Admin\UpdatePurchaserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'purchasers' => User::where('is_purchaser', true)->where('is_admin', false)
                ->select(['id', 'name', 'email', 'created_at'])->latest('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StorePurchaserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->forceFill(['is_admin' => false, 'is_purchaser' => true])->save();

        return redirect()->route('admin.users.index')
            ->with('status', "Purchaser account for {$user->name} has been created. You can now assign purchase orders to this account.");
    }

    public function edit(User $user): View
    {
        $this->ensurePurchaser($user);

        return view('admin.users.edit', ['purchaser' => $user]);
    }

    public function update(UpdatePurchaserRequest $request, User $user): RedirectResponse
    {
        $this->ensurePurchaser($user);
        $user->fill($request->safe()->only(['name', 'email']));

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
            $user->remember_token = null;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('admin.users.edit', $user)->with('status', 'Purchaser account updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensurePurchaser($user);

        DB::transaction(function () use ($user): void {
            $user->remember_token = null;
            $user->save();
            $user->delete();
        });

        return redirect()->route('admin.users.index')
            ->with('status', 'Purchaser account deleted. Past purchase orders remain available.');
    }

    private function ensurePurchaser(User $user): void
    {
        abort_unless($user->is_purchaser && ! $user->is_admin, 404);
    }
}
