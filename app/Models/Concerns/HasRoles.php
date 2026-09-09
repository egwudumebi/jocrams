<?php

namespace App\Models\Concerns;

use App\Enums\MemberStatus;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function hasRole(string $slug, ?string $guard = null): bool
    {
        return $this->roles()
            ->when($guard, fn ($query) => $query->where('guard_name', $guard))
            ->where('slug', $slug)
            ->exists();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::query()->where('slug', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        return Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $this->roles()->pluck('roles.id')))
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('super-admin', 'admin')
            || $this->roles()->where('guard_name', 'admin')->exists();
    }

    public function isActiveMember(): bool
    {
        return $this->member?->isActive() ?? false;
    }

    public function canJournalSubmit(): bool
    {
        return $this->isActiveMember() || $this->canJournalAssign();
    }

    public function canJournalReview(): bool
    {
        return $this->hasPermission('journal.review') || $this->canJournalAssign();
    }

    public function canJournalAssign(): bool
    {
        return $this->hasPermission('journal.assign');
    }

    public function canJournalPublish(): bool
    {
        return $this->hasPermission('journal.publish') || $this->canJournalAssign();
    }
}
