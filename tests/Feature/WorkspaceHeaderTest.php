<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        $person = User::factory()->create();
        $person->forceFill(['role' => $role])->save();

        return $person;
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new \DOMXPath($document);
    }

    public function test_workspace_header_has_sidebar_control_centered_search_and_icon_only_profile(): void
    {
        foreach (['admin' => 'dashboard', 'creator' => 'account', 'customer' => 'account'] as $role => $route) {
            $html = $this->actingAs($this->person($role))->get(route($route))->assertOk()->getContent();
            $xp = $this->xpath($html);
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//button[@data-workspace-sidebar-toggle and @aria-expanded="true" and @aria-controls="workspace-sidebar"]'));
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//input[@id="workspace-search-input" and @type="search"]'));
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//nav[@id="workspace-search-results"]'));
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//summary[contains(@class,"workspace-profile-trigger")]//svg'));
            $this->assertCount(0, $xp->query('//header[contains(@class,"workspace-top")]//div[contains(@class,"workspace-breadcrumb")]'));
            $this->assertCount(0, $xp->query('//div[contains(@class,"workspace-aside-footer")]'));
            $this->assertCount(0, $xp->query('//header[contains(@class,"workspace-top")]//summary[contains(@class,"workspace-profile-trigger")]//span'));
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//button[@data-theme-toggle]'));
            $this->assertCount(0, $xp->query('//div[contains(@class,"workspace-main")]/text()[normalize-space(.)=">"]'), 'Stray > must never appear below workspace header.');
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//a[contains(@class,"workspace-top-site") and @aria-label="Marketplace" and svg and not(normalize-space(text()))]'));
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//summary[contains(@class,"workspace-language-trigger")]//span[contains(@class,"workspace-language-flag")]'));
            $this->assertCount(count(config('samith.locales')), $xp->query('//header[contains(@class,"workspace-top")]//div[contains(@class,"workspace-language-list")]//a[span[contains(@class,"workspace-language-flag")]]'));
            $this->assertCount(1, $xp->query('//header[contains(@class,"workspace-top")]//form[@action="'.route('logout').'"]'));
            $this->assertGreaterThanOrEqual(6, $xp->query('//div[contains(@class,"workspace-profile-dropdown")]//a[svg]')->length);
            $this->assertCount(1, $xp->query('//div[contains(@class,"workspace-profile-dropdown")]//form/button[svg]'));
        }
    }

    public function test_admin_search_lists_real_admin_destinations_only_for_admin(): void
    {
        $admin = $this->person('admin');
        $xpath = $this->xpath($this->actingAs($admin)->get(route('dashboard.media'))->assertOk()->getContent());
        $this->assertCount(1, $xpath->query('//nav[@id="workspace-search-results"]//a[@href="'.route('dashboard.media').'"]'));
        $this->assertCount(1, $xpath->query('//nav[@id="workspace-search-results"]//a[@href="'.route('dashboard.security').'"]'));

        $customer = $this->person('customer');
        $xpath = $this->xpath($this->actingAs($customer)->get(route('account'))->assertOk()->getContent());
        $this->assertCount(0, $xpath->query('//nav[@id="workspace-search-results"]//a[@href="'.route('dashboard.security').'"]'));
        $this->assertCount(1, $xpath->query('//nav[@id="workspace-search-results"]//a[@href="'.route('account.settings').'"]'));
        $this->assertCount(0, $xpath->query('//nav[@id="workspace-search-results"]//a[@href="'.route('account.prompts').'"]'));

        $creator = $this->person('creator');
        $xpath = $this->xpath($this->actingAs($creator)->get(route('account'))->assertOk()->getContent());
        $this->assertCount(1, $xpath->query('//nav[@id="workspace-search-results"]//a[@href="'.route('account.prompts').'"]'));
    }
}
