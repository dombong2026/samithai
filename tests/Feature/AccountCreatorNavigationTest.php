<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCreatorNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function xp(string $html): \DOMXPath
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

    private function assertAccountDestinations(\DOMXPath $xp, bool $creator): void
    {
        $links = $xp->query('//nav[@aria-label="Account navigation"]//a[@href]');
        $destinations = [];
        foreach ($links as $link) {
            $destinations[] = $link->getAttribute('href');
        }

        $this->assertSame(count($destinations), count(array_unique($destinations)),
            'Each sidebar route must appear only once');

        $expected = [
            route('account'), route('account.library'), route('account.saved'),
            route('account.orders'), route('cart.index'), route('account.support'),
            route('account.settings'), route('prompts.index'),
        ];
        if ($creator) {
            array_push($expected, route('account.prompts'), route('account.media'),
                route('account.creator.insights'));
        }
        sort($expected);
        sort($destinations);
        $this->assertSame($expected, $destinations);

        $this->assertCount($creator ? 4 : 3,
            $xp->query('//nav[@aria-label="Account navigation"]//details[@data-nav-group]'));
        $this->assertCount($creator ? 1 : 0,
            $xp->query('//nav[@aria-label="Account navigation"]//details[@data-nav-group="creator-studio"]'));
        $this->assertCount(0, $xp->query('//nav[@aria-label="Administration navigation"]'));
    }

    public function test_customer_sidebar_groups_collection_purchases_and_help_without_creator_routes(): void
    {
        $customer = $this->account('customer');
        $this->actingAs($customer)->get(route('dashboard'))->assertForbidden();
        $this->get(route('account.creator.insights'))->assertForbidden();

        $html = $this->get(route('account'))->assertOk()
            ->assertSee('Account dashboard')
            ->assertSee('Welcome to your account')
            ->assertDontSee('Creator Studio')
            ->getContent();
        $xp = $this->xp($html);
        $this->assertAccountDestinations($xp, false);
        $this->assertCount(1, $xp->query('//details[@data-nav-group="collection" and @open]'));
        $this->assertCount(1, $xp->query('//nav[@aria-label="Account navigation"]//a[@aria-current="page" and @href="'.route('account').'"]'));

        foreach ([
            [route('account.saved'), 'collection'],
            [route('account.orders'), 'purchases'],
            [route('cart.index'), 'purchases'],
            [route('account.support'), 'account-care'],
            [route('account.settings'), 'account-care'],
        ] as [$url, $group]) {
            $response = $this->get($url)->assertOk();
            $xp = $this->xp($response->getContent());
            $this->assertCount(1, $xp->query('//details[@data-nav-group="'.$group.'" and @open]'));
        }
    }

    public function test_creator_sidebar_has_creator_studio_and_private_account_groups(): void
    {
        $creator = $this->account('creator');
        $html = $this->actingAs($creator)->get(route('account'))->assertOk()
            ->assertSee('Creator dashboard')
            ->assertSee('Your creator workspace')
            ->assertSee('Creator Studio')
            ->getContent();
        $xp = $this->xp($html);
        $this->assertAccountDestinations($xp, true);
        $this->assertCount(1, $xp->query('//details[@data-nav-group="creator-studio" and @open]'));

        foreach ([
            [route('account.prompts'), 'creator-studio'],
            [route('account.creator.insights'), 'creator-studio'],
            [route('account.media'), 'creator-studio'],
            [route('account.library'), 'collection'],
            [route('account.saved'), 'collection'],
            [route('account.settings'), 'account-care'],
        ] as [$url, $group]) {
            $html = $this->get($url)->assertOk()->getContent();
            $xp = $this->xp($html);
            $this->assertCount(1, $xp->query('//details[@data-nav-group="'.$group.'" and @open]'));
        }
    }

    public function test_admin_console_navigation_is_not_replaced_by_creator_menu(): void
    {
        $admin = $this->account('admin');
        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        $xp = $this->xp($html);
        $this->assertCount(5, $xp->query('//nav[@aria-label="Administration navigation"]//details[@data-nav-group]'));
        $this->assertCount(0, $xp->query('//nav[@aria-label="Account navigation"]'));
        $this->assertCount(0, $xp->query('//nav[@aria-label="Administration navigation"]//details[@data-nav-group="creator-studio"]'));
    }

    public function test_account_overview_has_only_real_counts_and_nonempty_group_headers(): void
    {
        $member = $this->account('customer');
        $response = $this->actingAs($member)->get(route('account'))->assertOk()
            ->assertSee('Licensed library')
            ->assertSee('Saved prompts')
            ->assertSee('Order records')
            ->assertSee('Library & saved')
            ->assertSee('Help & settings');
        $xp = $this->xp($response->getContent());
        $this->assertCount(1, $xp->query('//main[contains(concat(" ", normalize-space(@class), " "), " workspace-body ")]//div[contains(concat(" ", normalize-space(@class), " "), " account-dashboard-premium ")]'));
        foreach ($xp->query('//nav[@aria-label="Account navigation"]//details[@data-nav-group]') as $details) {
            $this->assertGreaterThan(0, $xp->query('.//a[@href]', $details)->count());
        }
    }
}
