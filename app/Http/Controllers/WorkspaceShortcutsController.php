<?php

namespace App\Http\Controllers;

use App\Models\WorkspacePreference;
use App\Support\AuditTrail;
use App\Support\WorkspaceLaunchpad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceShortcutsController extends Controller
{
    public function update(Request $request, string $context): RedirectResponse
    {
        $catalog = WorkspaceLaunchpad::catalog($request->user(), $context);
        $data = $request->validate([
            'shortcuts' => ['required', 'array', 'min:1', 'max:'.WorkspaceLaunchpad::MAX_PINNED],
            'shortcuts.*' => ['required', 'string', 'distinct', Rule::in(array_keys($catalog))],
        ]);

        WorkspacePreference::query()->updateOrCreate(
            ['user_id' => $request->user()->getKey(), 'context' => $context],
            ['shortcuts' => array_values($data['shortcuts'])]
        );
        AuditTrail::record($request, 'workspace.shortcuts.update', null, [
            'context' => $context, 'count' => count($data['shortcuts']),
        ]);
        return back()->with('success', 'Workspace shortcuts updated.');
    }

    public function reset(Request $request, string $context): RedirectResponse
    {
        WorkspaceLaunchpad::catalog($request->user(), $context);
        WorkspacePreference::query()->where('user_id', $request->user()->getKey())
            ->where('context', $context)->delete();
        AuditTrail::record($request, 'workspace.shortcuts.reset', null, ['context' => $context]);

        return back()->with('success', 'Default workspace shortcuts restored.');
    }
}
