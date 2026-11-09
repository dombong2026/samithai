<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ContentEntry;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\FrontendPageCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendPageAppearanceTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function fields(string $key): array
    {
        return FrontendPageCopy::values($key);
    }

    public function test_frontend_pages_table_lists_supported_templates_and_protected_content(): void
    {
        $admin = $this->person('admin');

        $this->actingAs($admin)->get(route('dashboard.appearance'))->assertOk()
            ->assertSee('Frontend Pages')
            ->assertSee('Global header & footer')
            ->assertSee('Homepage')
            ->assertSee('Browse prompts')
            ->assertSee('Category listing')
            ->assertSee('Prompt details')
            ->assertSee('Blog listing')
            ->assertSee('Policies listing')
            ->assertSee('Support Center')
            ->assertSee('Published legal license')
            ->assertSee('Add custom page')
            ->assertSee('Reset defaults')
            ->assertSee('frontend-config-home')
            ->assertSee('frontend-create-page')
            ->assertSee('Configure');

        $this->get(route('dashboard.appearance', ['page_q' => 'SUPPORT']))->assertOk()
            ->assertSee('Support Center')
            ->assertDontSee('Browse prompts');
    }

    public function test_only_administrators_can_configure_or_reset_builtin_pages(): void
    {
        $customer = $this->person('customer');
        $creator = $this->person('creator');
        $admin = $this->person('admin');

        foreach ([$customer, $creator] as $user) {
            $this->actingAs($user)->get(route('dashboard.appearance'))->assertForbidden();
            $this->put(route('dashboard.appearance.frontend.update', 'home'), [
                'fields' => $this->fields('home'),
            ])->assertForbidden();
            $this->delete(route('dashboard.appearance.frontend.reset', 'home'))->assertForbidden();
        }

        $this->actingAs($admin)->put(route('dashboard.appearance.frontend.update', 'not-a-template'), [
            'fields' => ['title' => 'Invalid'],
        ])->assertNotFound();
        $this->delete(route('dashboard.appearance.frontend.reset', 'invalid'))->assertNotFound();

        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_home_copy_can_be_edited_then_reset_without_deleting_any_site_records(): void
    {
        $admin = $this->person('admin');
        $fields = $this->fields('home');
        $fields['eyebrow'] = 'Curated by experts';
        $fields['primary_cta'] = 'Discover prompts now';
        $fields['show_categories'] = '0';
        $fields['show_recent'] = '0';

        $this->actingAs($admin)->put(route('dashboard.appearance.frontend.update', 'home'), [
            'fields' => $fields,
        ])->assertRedirect()->assertSessionHas('success');

        $stored = json_decode(SiteSetting::text('frontend_copy.home'), true);
        $this->assertSame('Curated by experts', $stored['eyebrow']);
        $this->assertFalse($stored['show_categories']);
        $this->assertFalse($stored['show_recent']);
        $this->assertDatabaseHas('audit_events', ['action' => 'frontend_page.configure', 'user_id' => $admin->id]);

        $this->get(route('home'))->assertOk()
            ->assertSee('Curated by experts')
            ->assertSee('Discover prompts now')
            ->assertDontSee('Explore categories')
            ->assertDontSee('Recently published prompts');

        $this->delete(route('dashboard.appearance.frontend.reset', 'home'))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('', SiteSetting::text('frontend_copy.home'));
        $this->get(route('home'))->assertOk()
            ->assertSee('Explore categories')
            ->assertSee('Recently published prompts')
            ->assertDontSee('Curated by experts');
        $this->assertDatabaseHas('audit_events', ['action' => 'frontend_page.reset', 'user_id' => $admin->id]);
    }

    public function test_page_editor_rejects_invalid_text_toggles_and_ignores_unknown_attributes(): void
    {
        $admin = $this->person('admin');
        $fields = $this->fields('catalog');
        $fields['title'] = str_repeat('X', 451);
        $this->actingAs($admin)->put(route('dashboard.appearance.frontend.update', 'catalog'), [
            'fields' => $fields,
        ])->assertSessionHasErrors('fields.title');

        $fields['title'] = 'Curated prompts for teams';
        $fields['show_filters'] = 'yes-please';
        $this->put(route('dashboard.appearance.frontend.update', 'catalog'), [
            'fields' => $fields,
        ])->assertSessionHasErrors('fields.show_filters');

        $fields['show_filters'] = '1';
        $fields['unsafe_admin_html'] = '<script>alert(1)</script>';
        $this->put(route('dashboard.appearance.frontend.update', 'catalog'), [
            'fields' => $fields,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('unsafe_admin_html',
            json_decode(SiteSetting::text('frontend_copy.catalog'), true));
        $this->get(route('prompts.index'))->assertOk()
            ->assertSee('Curated prompts for teams')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_custom_frontend_page_has_create_edit_preview_publish_unpublish_delete_crud(): void
    {
        $admin = $this->person('admin');
        $customer = $this->person('customer');
        $payload = [
            'title' => 'About our marketplace',
            'excerpt' => 'The platform mission',
            'body_html' => '<h2>Who we are</h2><script>alert(1)</script>',
        ];

        $this->actingAs($customer)->post(route('dashboard.content.store', 'page'), $payload)
            ->assertForbidden();
        $this->actingAs($admin)->post(route('dashboard.content.store', 'page'), $payload)
            ->assertRedirect();
        $entry = ContentEntry::where('kind', 'page')->firstOrFail();
        $this->assertSame('draft', $entry->status);
        $this->assertStringNotContainsString('<script', $entry->body_html);

        $this->get(route('dashboard.appearance'))->assertOk()
            ->assertSee('About our marketplace')
            ->assertSee('frontend-custom-'.$entry->id)
            ->assertSee('/pages/'.$entry->slug)
            ->assertSee('Publish');
        $this->get(route('dashboard.content.preview', $entry))->assertOk();
        $this->get(route('pages.show', $entry->slug))->assertNotFound();

        $this->put(route('dashboard.content.update', $entry), [
            'title' => 'Marketplace mission',
            'excerpt' => 'New copy',
            'body_html' => '<p>Committed to responsible prompt licensing.</p>',
        ])->assertRedirect();
        $this->post(route('dashboard.content.publish', $entry), ['status' => 'published'])
            ->assertRedirect();
        $this->get(route('pages.show', $entry->slug))->assertOk()
            ->assertSee('Marketplace mission')
            ->assertSee('Committed to responsible prompt licensing.');

        $this->post(route('dashboard.content.publish', $entry), ['status' => 'draft'])
            ->assertRedirect();
        $this->get(route('pages.show', $entry->slug))->assertNotFound();
        $this->delete(route('dashboard.content.destroy', $entry))->assertRedirect();
        $this->assertDatabaseMissing('content_entries', ['id' => $entry->id]);
    }

    public function test_page_copy_changes_global_shell_and_support_without_legal_override(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin);

        $shell = $this->fields('shell');
        $shell['top_strip'] = 'Private prompt studio';
        $shell['search_placeholder'] = 'Search the library';
        $this->put(route('dashboard.appearance.frontend.update', 'shell'), [
            'fields' => $shell,
        ])->assertRedirect();

        $support = $this->fields('support');
        $support['title'] = 'We are here to help';
        $this->put(route('dashboard.appearance.frontend.update', 'support'), [
            'fields' => $support,
        ])->assertRedirect();

        $this->get(route('support.center'))->assertOk()->assertSee('We are here to help')
            ->assertSee('Private prompt studio')
            ->assertSee('Search the library');
        $this->get(route('home'))->assertOk()->assertSee('Private prompt studio');

        // The page-copy schema contains no legal body field and cannot replace governed legal documents.
        $this->assertArrayNotHasKey('body_html', FrontendPageCopy::definition('policies')['fields']);
    }

    public function test_auth_page_headings_are_editable_but_security_routes_remain_unchanged(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin);

        foreach ([
            'login' => 'Welcome to the prompt studio',
            'register' => 'Join the creator community',
            'forgot' => 'Recover your account securely',
            'reset' => 'Choose a secure password now',
            'verify' => 'Verify your account email',
        ] as $key => $title) {
            $fields = $this->fields($key);
            $fields['title'] = $title;
            $this->put(route('dashboard.appearance.frontend.update', $key), [
                'fields' => $fields,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('Welcome to the prompt studio');
        $this->get(route('register'))->assertOk()->assertSee('Join the creator community')
            ->assertSee('minlength="10"', false);
        $this->get(route('password.request'))->assertOk()
            ->assertSee('Recover your account securely');
        $this->get('/reset-password/example-token')->assertOk()
            ->assertSee('Choose a secure password now')
            ->assertSee('minlength="12"', false);

        $customer = $this->person('customer');
        $this->actingAs($customer)->get(route('verification.notice'))->assertOk()
            ->assertSee('Verify your account email');
    }

    public function test_global_success_and_error_feedback_has_closing_controls_and_accessible_roles(): void
    {
        $this->withSession(['success' => 'Saved successfully.'])->get(route('home'))->assertOk()
            ->assertSee('Saved successfully.')
            ->assertSee('data-toast-dismiss', false)
            ->assertSee('role="status"', false);

        $this->withSession(['errors' => session()->get('errors') ?? new \Illuminate\Support\ViewErrorBag()])
            ->get(route('home'))->assertOk();
    }

    public function test_optional_catalog_page_can_be_disabled_and_reenabled(): void
    {
        $admin = $this->person('admin');
        $customer = $this->person('customer');
        $this->actingAs($customer)->put(route('dashboard.appearance.frontend.visibility','catalog'), [
            'enabled' => '0',
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('dashboard.appearance.frontend.visibility','catalog'), [
            'enabled' => '0',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('0', SiteSetting::text('frontend_page.enabled.catalog'));
        $this->assertDatabaseHas('audit_events', ['action'=>'frontend_page.visibility','user_id'=>$admin->id]);
        $this->get(route('prompts.index'))->assertNotFound();
        $this->get(route('locale.prompts.index',['locale'=>'en']))->assertNotFound();
        $this->get(route('home'))->assertOk();
        $this->get(route('dashboard.appearance'))->assertOk()->assertSee('frontend-visibility-button');

        $this->put(route('dashboard.appearance.frontend.visibility','catalog'), ['enabled'=>'1'])
            ->assertRedirect();
        $this->get(route('prompts.index'))->assertOk();
    }

    public function test_protected_pages_cannot_be_disabled_even_by_admin(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin);
        foreach (['shell','home','policies','login','forgot','reset','verify','policy_detail','missing'] as $key) {
            $this->put(route('dashboard.appearance.frontend.visibility',$key),[
                'enabled'=>'0',
            ])->assertStatus(422);
        }
        $this->put(route('dashboard.appearance.frontend.visibility','blog'),[
            'enabled'=>'invalid',
        ])->assertSessionHasErrors('enabled');
        $this->assertDatabaseCount('site_settings',0);
        $this->get(route('home'))->assertOk();
        auth()->logout();
        $this->get(route('login'))->assertOk();
        $this->get(route('password.request'))->assertOk();
        $this->get(route('policies.index'))->assertOk();
    }

    public function test_registration_switch_blocks_both_public_form_and_submission(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->put(route('dashboard.appearance.frontend.visibility','register'),[
            'enabled'=>'0',
        ])->assertRedirect();
        auth()->logout();
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.submit'),[
            'name'=>'Blocked Customer','email'=>'blocked@example.test',
            'password'=>'SecurePassword123','password_confirmation'=>'SecurePassword123',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users',['email'=>'blocked@example.test']);
        $this->get(route('login'))->assertOk();
        $this->actingAs($admin)->put(route('dashboard.appearance.frontend.visibility','register'),[
            'enabled'=>'1',
        ])->assertRedirect();
        auth()->logout();
        $this->get(route('register'))->assertOk();
    }
}
