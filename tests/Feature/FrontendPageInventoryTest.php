<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\FrontendPageCopy;
use App\Support\FrontendPageRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FrontendPageInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function test_every_named_browser_frontend_page_is_registered_in_appearance_inventory(): void
    {
        $covered = FrontendPageRegistry::coveredBrowserRoutes();
        $this->assertSame($covered, array_values(array_unique($covered)));

        $excludedOperations = [
            'language.switch', 'installer.show', 'media.serve', 'storage.local',
            'verification.verify',
        ];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods(), true)) continue;
            $name = $route->getName();
            if (!$name || $name === 'dashboard' || str_starts_with($name, 'dashboard.')) continue;
            if (in_array($name, $excludedOperations, true)) continue;

            // Locale-specific GETs share their public templates.
            if (str_starts_with($name, 'locale.')) $name = substr($name, 7);

            $this->assertContains($name, $covered,
                'A customer-facing GET page is missing from Appearance: '.$route->uri());
        }

        $expected = [
            'home', 'prompts.index', 'prompts.show', 'categories.show', 'pages.show',
            'blog.index', 'blog.show', 'policies.index', 'policies.show',
            'support.center', 'support.articles.show', 'legal.show',
            'login', 'register', 'password.request', 'password.reset',
            'verification.notice', 'cart.index', 'account', 'account.library',
            'account.library.show', 'account.saved', 'account.orders',
            'orders.show', 'account.settings', 'account.prompts',
            'account.prompts.reviews', 'account.prompts.quality', 'account.prompt-quality',
            'dashboard.prompt-quality', 'account.creator.insights',
            'account.support', 'support.show', 'account.media', 'workspace.tasks',
        ];
        $this->assertEqualsCanonicalizing($expected, $covered);
        foreach (FrontendPageRegistry::protected() as $entry) {
            $this->assertTrue(Route::has($entry['action_route']),
                'Missing real management route for '.$entry['name']);
        }
        foreach (FrontendPageRegistry::managed() as $entry) {
            $this->assertTrue(Route::has('dashboard.content'));
        }
    }

    public function test_admin_can_find_all_frontend_page_types_and_real_management_links(): void
    {
        $admin = $this->person('admin');

        $this->actingAs($admin)->get(route('dashboard.appearance'))->assertOk()
            ->assertSee('Frontend Pages')
            ->assertSee('Shopping cart')
            ->assertSee('Global header & footer')
            ->assertSee('Homepage')
            ->assertSee('Browse prompts')
            ->assertSee('Blog article detail')
            ->assertSee('Custom page detail template')
            ->assertSee('Published legal license')
            ->assertSee('Account overview')
            ->assertSee('My prompt library')
            ->assertSee('Creator prompt manager')
            ->assertSee('Creator media library')
            ->assertSee('Support conversation')
            ->assertSee('Personal tasks')
            ->assertSee('Account & protected', false)
            ->assertSee('Add custom page');

        $this->get(route('dashboard.appearance', ['page_type' => 'system']))->assertOk()
            ->assertSee('Creator insights')
            ->assertSee('Protected')
            ->assertDontSee('frontend-config-home')
            ->assertDontSee('frontend-config-cart');
        $this->get(route('dashboard.appearance', ['page_type' => 'builtin']))->assertOk()
            ->assertSee('frontend-config-cart')
            ->assertDontSee('Creator media library');
        $this->get(route('dashboard.appearance', ['page_type' => 'managed']))->assertOk()
            ->assertSee('Custom page detail template')
            ->assertSee('Blog article detail')
            ->assertDontSee('Shopping cart');
        $this->get(route('dashboard.appearance', ['page_type' => 'custom']))->assertOk()
            ->assertSee('Add custom page')
            ->assertDontSee('frontend-config-cart');
    }

    public function test_search_includes_account_names_paths_and_types(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin);

        $this->get(route('dashboard.appearance', ['page_q' => 'creator media']))
            ->assertOk()->assertSee('Creator media library')->assertDontSee('Blog article detail');
        $this->get(route('dashboard.appearance', ['page_q' => '/workspace/tasks']))
            ->assertOk()->assertSee('Personal tasks')->assertDontSee('Shopping cart');
        $this->get(route('dashboard.appearance', ['page_q' => '/cart']))
            ->assertOk()->assertSee('Shopping cart')->assertDontSee('Blog article detail');
        $this->get(route('dashboard.appearance', ['page_q' => 'xxx-no-such-page']))
            ->assertOk()->assertSee('No matching frontend pages.');
        $this->get(route('dashboard.appearance', ['page_type' => 'unknown']))
            ->assertSessionHasErrors('page_type');

        ContentEntry::create([
            'user_id' => $admin->id, 'kind' => 'page', 'title' => 'Company mission',
            'slug' => 'company-mission', 'body_html' => '<p>Company mission</p>',
            'status' => 'draft',
        ]);
        $this->get(route('dashboard.appearance', ['page_type' => 'custom']))->assertOk()
            ->assertSee('Company mission')->assertSee('frontend-custom-', false);
    }

    public function test_cart_copy_is_configurable_but_checkout_and_access_cannot_be_disabled(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin);

        $fields = FrontendPageCopy::values('cart');
        $fields['title'] = 'Your prompt collection basket';
        $fields['empty_title'] = 'Nothing saved for checkout yet';
        $fields['browse_cta'] = 'Discover prompts';

        $this->put(route('dashboard.appearance.frontend.update', 'cart'), [
            'fields' => $fields,
        ])->assertRedirect()->assertSessionHas('success');

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Your prompt collection basket')
            ->assertSee('Nothing saved for checkout yet')
            ->assertSee('Discover prompts');
        $this->assertSame('Your prompt collection basket',
            json_decode(SiteSetting::text('frontend_copy.cart'), true)['title']);

        $this->put(route('dashboard.appearance.frontend.visibility', 'cart'), [
            'enabled' => '0',
        ])->assertStatus(422);
        $this->get(route('cart.index'))->assertOk();

        $this->delete(route('dashboard.appearance.frontend.reset', 'cart'))
            ->assertRedirect()->assertSessionHas('success');
        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Shopping cart')->assertSee('Your cart is empty');
        $this->assertArrayNotHasKey('checkout_override', FrontendPageCopy::definition('cart')['fields']);
    }

    public function test_non_admins_cannot_edit_or_view_appearance_inventory(): void
    {
        foreach (['customer', 'creator'] as $role) {
            $user = $this->person($role);
            $this->actingAs($user)->get(route('dashboard.appearance',
                ['page_type' => 'system']))->assertForbidden();
            $this->put(route('dashboard.appearance.frontend.update', 'cart'), [
                'fields' => FrontendPageCopy::values('cart'),
            ])->assertForbidden();
        }
    }
}
