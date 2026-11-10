<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        $old = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadHTML($html));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
        return new \DOMXPath($document);
    }

    public function test_admin_sidebar_groups_each_real_route_once_and_contains_no_leaked_sections(): void
    {
        $admin = $this->person('admin');
        $html = $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()->assertSee('Marketplace dashboard')->getContent();
        $xpath = $this->xpath($html);
        $this->assertCount(1, $xpath->query('//nav[@aria-label="Administration navigation"]'));
        $this->assertCount(5, $xpath->query('//nav[@aria-label="Administration navigation"]//details[@data-nav-group]'));
        $destinations = [];
        foreach ($xpath->query('//nav[@aria-label="Administration navigation"]//a[@href]') as $link) {
            $destinations[] = $link->getAttribute('href');
        }
        $this->assertSame(count($destinations), count(array_unique($destinations)), 'Sidebar destinations must be unique');
        foreach ([
            route('dashboard'), route('dashboard.prompts'), route('dashboard.categories'),
            route('dashboard.users'), route('dashboard.content', 'posts'),
            route('dashboard.content', 'pages'), route('dashboard.content', 'policies'),
            route('dashboard.content', 'knowledge'), route('dashboard.media'),
            route('dashboard.finance'), route('dashboard.legal'),
            route('dashboard.support'), route('dashboard.appearance'),
            route('dashboard.footer'), route('dashboard.languages'),
            route('dashboard.security'), route('dashboard.system'),
            route('account'), route('workspace.tasks'), route('prompts.index'),
        ] as $destination) {
            $this->assertContains($destination, $destinations);
        }
        $this->assertCount(1, $xpath->query('//main[contains(concat(" ",normalize-space(@class)," ")," workspace-body ")]//section[@id="marketplace-interest"]'));
        $this->assertCount(1, $xpath->query('//main[contains(concat(" ",normalize-space(@class)," ")," workspace-body ")]//section[@aria-label="Marketplace command center"]'));
    }

    public function test_active_navigation_section_expands_and_nonadmins_do_not_receive_admin_links(): void
    {
        $admin = $this->person('admin');
        $html = $this->actingAs($admin)->get(route('dashboard.prompts'))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $this->assertCount(1, $xpath->query('//details[@data-nav-group="marketplace" and @open]'));
        $this->assertCount(1, $xpath->query('//nav[@aria-label="Administration navigation"]//a[@aria-current="page" and @href="'.route('dashboard.prompts').'"]'));

        $html = $this->get(route('dashboard.content', 'posts'))->assertOk()->getContent();
        $this->assertCount(1, $this->xpath($html)->query('//details[@data-nav-group="content" and @open]'));

        $customer = $this->person('customer');
        $this->actingAs($customer)->get(route('dashboard'))->assertForbidden();
        $customerHtml = $this->get(route('account'))->assertOk()->getContent();
        $xpath = $this->xpath($customerHtml);
        $this->assertCount(0, $xpath->query('//nav[@aria-label="Administration navigation"]'));
        $this->assertCount(1, $xpath->query('//nav[@aria-label="Account navigation"]'));
        $this->assertCount(0, $xpath->query('//nav[@aria-label="Account navigation"]//a[@href="'.route('dashboard.prompts').'"]'));
        $this->assertCount(1, $xpath->query('//nav[@aria-label="Account navigation"]//a[@href="'.route('account.saved').'"]'));
    }

    public function test_dashboard_uses_real_database_activity_and_never_shows_licensed_prompt_content(): void
    {
        $admin = $this->person('admin');
        $creator = $this->person('creator');
        $customer = $this->person('customer');
        $category = Category::create(['name' => 'Writing', 'slug' => 'writing', 'is_active' => true]);
        $approved = Prompt::create([
            'user_id' => $creator->id, 'category_id' => $category->id,
            'title' => 'Published writing helper', 'slug' => 'published-writing-helper',
            'summary' => 'Public summary', 'content' => 'PRIVATE_PROMPT_BODY_DO_NOT_EXPOSE',
            'price' => 0, 'license_type' => 'regular',
            'status' => 'published', 'published_at' => now(),
        ]);
        Prompt::create([
            'user_id' => $creator->id, 'category_id' => $category->id,
            'title' => 'Pending writing helper', 'slug' => 'pending-writing-helper',
            'summary' => 'Public preview', 'content' => 'PRIVATE_PENDING_BODY',
            'price' => 0, 'license_type' => 'regular', 'status' => 'pending',
        ]);
        DB::table('prompt_saves')->insert([
            'user_id' => $customer->id, 'prompt_id' => $approved->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        SupportTicket::create(['user_id' => $customer->id, 'subject' => 'Support question', 'status' => 'open']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('1 pending approvals')
            ->assertSee('1 open support tickets')
            ->assertSee('Published writing helper')
            ->assertSee('Pending writing helper')
            ->assertSee('1 saves')
            ->assertDontSee('PRIVATE_PROMPT_BODY_DO_NOT_EXPOSE')
            ->assertDontSee('PRIVATE_PENDING_BODY');
    }

    public function test_empty_marketplace_shows_useful_setup_steps_instead_of_fake_metrics(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Start with your first category')
            ->assertSee('Create category')
            ->assertSee('No prompt submissions yet')
            ->assertSee('No saved prompts yet');
    }
}
