<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\AuditTrail;
use App\Support\FrontendPageCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FrontendPageController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $definition = FrontendPageCopy::definition($page);
        abort_unless($definition, 404);

        $rules = ['fields' => ['required', 'array']];
        foreach ($definition['fields'] as $name => $field) {
            $rules['fields.'.$name] = ($field['type'] ?? '') === 'toggle'
                ? ['required', 'boolean']
                : ['required', 'string', 'max:450'];
        }

        $data = $request->validate($rules);
        $values = [];
        foreach ($definition['fields'] as $name => $field) {
            $values[$name] = ($field['type'] ?? '') === 'toggle'
                ? (bool) $data['fields'][$name]
                : trim($data['fields'][$name]);
        }
        SiteSetting::updateOrCreate(
            ['key' => 'frontend_copy.'.$page],
            ['value' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]
        );

        AuditTrail::record($request, 'frontend_page.configure', null, ['page' => $page]);
        return back()->with('success', $definition['name'].' updated.');
    }

    public function reset(Request $request, string $page): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $definition = FrontendPageCopy::definition($page);
        abort_unless($definition, 404);

        SiteSetting::query()->whereKey('frontend_copy.'.$page)->delete();
        AuditTrail::record($request, 'frontend_page.reset', null, ['page' => $page]);
        return back()->with('success', $definition['name'].' restored to defaults.');
    }
}
