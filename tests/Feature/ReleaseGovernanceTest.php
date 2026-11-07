<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\ContentTranslation;
use App\Models\LegalVersion;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role='customer'): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user;
    }

    public function test_unreviewed_legal_drafts_cannot_be_published_without_attestation(): void
    {
        $admin=$this->person('admin');
        $user=$this->person();
        $body='<p>'.str_repeat('This document requires independent legal review. ',4).'</p>';
        $payload=[
            'kind'=>'regular','version'=>'1.0.0','title'=>'Regular License Draft',
            'body_html'=>$body,
        ];
        $this->actingAs($user)->post(route('dashboard.legal.store'),$payload)->assertForbidden();
        $this->actingAs($admin)->post(route('dashboard.legal.store'),$payload)->assertRedirect();
        $document=LegalVersion::firstOrFail();
        $this->get(route('legal.show','regular'))->assertNotFound();
        $this->post(route('dashboard.legal.publish',$document),[
            'review_reference'=>'test review only',
        ])->assertSessionHasErrors('acknowledged');
        $this->assertSame('draft',$document->fresh()->status);
        $this->post(route('dashboard.legal.publish',$document),[
            'review_reference'=>'review-reference-2026-001',
            'acknowledged'=>'1',
        ])->assertRedirect();
        $this->get(route('legal.show','regular'))->assertOk()->assertSee('Regular License Draft');
        $this->put(route('dashboard.legal.update',$document),$payload)->assertStatus(422);
        $this->assertDatabaseHas('audit_events',['action'=>'legal.publish','subject_id'=>$document->id]);
    }

    public function test_free_checkout_requires_exact_published_legal_terms_when_gate_enabled(): void
    {
        config(['samith.require_legal_acceptance'=>true]);
        $creator=$this->person('creator');
        $buyer=$this->person();
        $category=Category::create(['name'=>'Tools','slug'=>'tools','is_active'=>true]);
        $prompt=Prompt::create([
            'user_id'=>$creator->id,'category_id'=>$category->id,'title'=>'Prompt kit',
            'slug'=>'prompt-kit','summary'=>'Practical writing kit','content'=>'PRIVATE PROMPT',
            'price'=>0,'status'=>'published','license_type'=>'regular',
        ]);

        $this->actingAs($buyer)->post(route('cart.add',$prompt))->assertRedirect();
        $this->post(route('checkout'),['accept_legal_terms'=>'1'])->assertSessionHasErrors('cart');

        foreach (['regular','marketplace_terms'] as $kind) {
            LegalVersion::create([
                'kind'=>$kind,'version'=>'1.0.0',
                'title'=>ucfirst($kind).' Terms','body_html'=>str_repeat('Reviewed terms. ',10),
                'status'=>'published','review_reference'=>'test-reviewed-only',
                'effective_at'=>now(),'reviewed_at'=>now(),
            ]);
        }
        $this->post(route('checkout'))->assertSessionHasErrors('cart');
        $this->post(route('checkout'),['accept_legal_terms'=>'1'])
            ->assertRedirect(route('account.library'));
        $this->assertDatabaseCount('legal_acceptances',2);
        $this->assertDatabaseCount('prompt_entitlements',1);
    }

    public function test_draft_translations_do_not_override_source_and_reviewed_ones_do(): void
    {
        $admin=$this->person('admin');
        $user=$this->person();
        $entry=ContentEntry::create([
            'user_id'=>$admin->id,'kind'=>'page','title'=>'About', 'slug'=>'about',
            'excerpt'=>'English summary','body_html'=>'<p>English body</p>',
            'status'=>'published','published_at'=>now(),
        ]);
        $data=['title'=>'À propos','excerpt'=>'Résumé français','body_html'=>'<p>Corps français</p>'];
        $this->actingAs($user)->put(route('dashboard.translations.update',[$entry,'fr']),$data)
            ->assertForbidden();
        $this->actingAs($admin)->put(route('dashboard.translations.update',[$entry,'fr']),$data)
            ->assertRedirect();
        $this->get('/fr/pages/about')->assertOk()->assertSee('English body')->assertDontSee('Corps français');
        $this->get(route('pages.show','about'))->assertSee('English body');
        $translation=ContentTranslation::firstOrFail();
        $this->assertSame('draft',$translation->status);
        $this->post(route('dashboard.translations.review',[$entry,'fr']),['decision'=>'approve'])
            ->assertRedirect();
        $this->assertSame('published',$translation->fresh()->status);
        $this->get('/fr/pages/about')->assertOk()->assertSee('Corps français')->assertDontSee('English body');
    }
}
