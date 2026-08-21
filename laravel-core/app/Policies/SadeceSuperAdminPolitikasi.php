<?php

namespace App\Policies;

use App\Models\User;

/**
 * SaaS çekirdek yönetim kaynakları: yalnızca süper admin.
 */
class SadeceSuperAdminPolitikasi
{
    public function viewAny(User $kullanici): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function view(User $kullanici, mixed $model): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function create(User $kullanici): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function update(User $kullanici, mixed $model): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function delete(User $kullanici, mixed $model): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function restore(User $kullanici, mixed $model): bool
    {
        return $this->superAdminMi($kullanici);
    }

    public function forceDelete(User $kullanici, mixed $model): bool
    {
        return $this->superAdminMi($kullanici);
    }

    protected function superAdminMi(User $kullanici): bool
    {
        return (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
    }
}
