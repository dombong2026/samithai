<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\FooterColumn;
use App\Models\MediaAsset;
use App\Models\Prompt;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SafeHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformModulesTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role='customer'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user;
    }

    public function test_media_library_restricts_ownership_and_validates_format(): void
    {
        Storage::fake('local');
        $creator = $this->person('creator');
        $stranger = $this->person('creator');
        $admin = $this->person('admin');

        $this->actingAs($creator)->post(route('media.reference'), [
            'title'=>'Orange', 'kind'=>'color', 'color_hex'=>'#ed702b',
        ])->assertRedirect();
        $media = MediaAsset::firstOrFail();
        $this->assertSame('#ED702B',$media->color_hex);
        $this->actingAs($stranger)->put(route('media.update',$media),['title'=>'Hijacked'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('media.destroy',$media))->assertForbidden();
        $this->actingAs($admin)->put(route('media.update',$media),['title'=>'Approved'])->assertRedirect();
        $this->assertSame('Approved',$media->fresh()->title);

        $this->actingAs($creator)->post(route('media.reference'),[
            'title'=>'Dangerous', 'kind'=>'youtube', 'url'=>'https://evil.example/watch?v=abcdefghijk',
        ])->assertSessionHasErrors('url');

        $this->actingAs($creator)->post(route('media.upload'),[
            'file' => UploadedFile::fake()->createWithContent('attack.svg','<svg onload="alert(1)"></svg>'),
        ])->assertSessionHasErrors('file');
        $this->assertSame(1, MediaAsset::count());
    }

    public function test_safe_html_strips_scripts_events_and_unsafe_links(): void
    {
        $clean = SafeHtml::clean('<p onclick="alert(1)">Hello <strong>world</strong><script>alert(1)</script><a href="javascript:alert(1)">bad</a><a href="https://example.com" onclick="x()">good</a></p>');
        $this->assertStringContainsString('<strong>world</strong>',$clean);
        $this->assertStringNotContainsString('onclick',$clean);
        $this->assertStringNotContainsString('<script',$clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('noopener',$clean);
    }

    public function test_content_requires_admin_and_draft_is_not_public(): void
    {
        $admin = $this->person('admin'); $customer = $this->person();
        $payload=['title'=>'Privacy', 'body_html'=>'<h2>Protection</h2><script>alert(1)</script>',
            'excerpt'=>'Site privacy', 'featured_media_id'=>null];
        $this->actingAs($customer)->post(route('dashboard.content.store','policy'),$payload)->assertForbidden();
        $this->actingAs($admin)->post(route('dashboard.content.store','policy'),$payload)->assertRedirect();
        $entry = ContentEntry::firstOrFail();
        $this->assertSame('draft',$entry->status);
        $this->assertStringNotContainsString('<script',$entry->body_html);

        $this->get(route('policies.show',$entry->slug))->assertNotFound();
        $this->get(route('dashboard.content.preview',$entry))->assertOk();
        $this->post(route('dashboard.content.publish',$entry),['status'=>'published'])->assertRedirect();
        $this->get(route('policies.show',$entry->slug))->assertOk()->assertDontSee('alert(1)');
    }

    public function test_footer_links_enforce_https_and_relative_urls(): void
    {
        $admin=$this->person('admin');
        $this->actingAs($admin)->post(route('dashboard.footer.store'),
            ['title'=>'Help','position'=>1,'enabled'=>1])->assertRedirect();
        $column=FooterColumn::firstOrFail();
        $this->post(route('dashboard.footer.links.store',$column),
            ['label'=>'Bad','url'=>'javascript:alert(1)','position'=>1])->assertSessionHasErrors('url');
        $this->post(route('dashboard.footer.links.store',$column),
            ['label'=>'Help center','url'=>'/support-center','position'=>1])->assertRedirect();
        $this->get(route('home'))->assertOk()->assertSee('Help center');
    }

    public function test_support_restricts_ticket_access_and_allows_replies(): void
    {
        $owner=$this->person();$stranger=$this->person();$admin=$this->person('admin');
        $this->actingAs($owner)->post(route('support.store'),[
            'subject'=>'Question about licensed prompts','body'=>'Please explain my library license.',
        ])->assertRedirect();
        $ticket=SupportTicket::firstOrFail();
        $this->assertSame(1,$ticket->messages()->count());
        $this->actingAs($stranger)->get(route('support.show',$ticket))->assertForbidden();
        $this->actingAs($stranger)->post(route('support.reply',$ticket),['body'=>'Not mine'])->assertForbidden();
        $this->actingAs($admin)->post(route('support.reply',$ticket),['body'=>'We can help.'])->assertRedirect();
        $this->assertSame(2,$ticket->messages()->count());
        $this->post(route('support.status',$ticket),['status'=>'closed'])->assertRedirect();
        $this->actingAs($owner)->post(route('support.reply',$ticket),['body'=>'More'])->assertStatus(422);
        $this->actingAs($owner)->post(route('support.status',$ticket),['status'=>'open'])->assertForbidden();
    }

    public function test_admin_only_finance_security_health_and_languages(): void
    {
        $customer=$this->person();$admin=$this->person('admin');
        foreach (['dashboard.finance','dashboard.security','dashboard.system','dashboard.languages'] as $name) {
            $this->actingAs($customer)->get(route($name))->assertForbidden();
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
        $this->get(route('locale.home',['locale'=>'km']))->assertOk();
        $this->get(route('locale.prompts.index',['locale'=>'ar']))->assertOk();
        $this->get('/xx/prompts')->assertNotFound();
    }
}
