<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Support\Collection;

final class WorkspaceActivity
{
    // No raw audit details, IP hashes, subjects or restricted prompt data.
    private const LABELS = [
        'auth.login' => 'Signed in',
        'account.profile.update' => 'Updated account details',
        'account.password.update' => 'Changed account password',
        'auth.password.reset' => 'Reset account password',
        'user.role.update' => 'Updated an account role',
        'frontend_page.visibility' => 'Changed public page availability',
        'workspace.shortcuts.update' => 'Customized workspace shortcuts',
        'workspace.shortcuts.reset' => 'Reset workspace shortcuts',
    ];

    public static function forUser(int $userId): Collection
    {
        return self::summarize(
            AuditEvent::query()->where('user_id', $userId)
                ->whereIn('action', array_keys(self::LABELS))
                ->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()
        );
    }

    public static function forAdmin(): Collection
    {
        return self::summarize(
            AuditEvent::query()->whereIn('action', [
                'user.role.update', 'frontend_page.visibility',
                'workspace.shortcuts.update', 'workspace.shortcuts.reset',
            ])->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()
        );
    }

    private static function summarize(Collection $events): Collection
    {
        return $events->map(fn ($event) => [
            'label' => self::LABELS[$event->action],
            'time' => $event->created_at,
        ]);
    }
}
