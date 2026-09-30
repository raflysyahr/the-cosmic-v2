<?php

namespace App\Modules\Discuss\Services\Concerns;

use App\Modules\Auth\Enums\UserRole;
use App\Modules\Auth\Models\User;
use Illuminate\Validation\ValidationException;

trait AuthorizesCpAdmin
{
    /**
     * Pengaturan CP bersifat global (lintas room), jadi hanya admin
     * platform (users.role = admin) yang boleh — bukan admin per-room.
     */
    protected function assertCpAdmin(User $actor): void
    {
        if ($actor->role !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'admin' => ['Only administrators can manage Contribution Points.'],
            ]);
        }
    }
}
