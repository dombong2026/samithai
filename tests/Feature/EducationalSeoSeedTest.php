<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\SeoMetadata;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducationalSeoSeedTest extends TestCase
{
    use RefreshDatabase;

    private function setupOwnerAndContent(): User
    {
        $admin=User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        foreach ([
            'business-productivity', 'education-teaching', 'coding-development',
            'marketing-social-media', 'ecommerce-sales', 'data-research',
        ] as $slug) {
            Category::create(['slug'=>$slug,'name'=>ucwords(str_replace('-',' ',$slug)),'is_active'=>true]);
        }
        $this->artisan('samithai:import-educational-blogs', [
            '--apply'=>true,'--author-email'=>$admin->email,
        ])->assertSuccessful();
        return $admin;
    }

    private function args(User $owner,string $action): array
    {
        return ['--'.$action=>true,'--author-email'=>$owner->email];
    }

    public function test_seo_seed_is_read_only_until_explicit_apply(): void
    {
        $owner=$this->setupOwnerAndContent();
        $this->artisan('samithai:seed-educational-seo',$this->args($owner,'check'))
            ->expectsOutput('EDUCATIONAL_SEO_BUNDLE=8_VERIFIED')
            ->expectsOutput('EXISTING_MATCHING_SEO=0')
            ->expectsOutput('NEW_SEO_RECORDS_TO_CREATE=8')
            ->expectsOutput('READ_ONLY_SEO_PREFLIGHT=PASSED')
            ->assertSuccessful();
        $this->assertDatabaseCount('seo_metadata',0);
        $this->assertSame(8,ContentEntry::where('status','draft')->count());
    }

    public function test_seo_import_creates_eight_records_without_publication_and_is_idempotent(): void
    {
        $owner=$this->setupOwnerAndContent();
        $this->artisan('samithai:seed-educational-seo',$this->args($owner,'apply'))
            ->expectsOutput('EDUCATIONAL_SEO_IMPORT=SUCCESS')
            ->expectsOutput('NEW_SEO_RECORDS_CREATED=8')
            ->expectsOutput('CONTENT_PUBLICATION_CHANGES=0')->assertSuccessful();
        $this->assertDatabaseCount('seo_metadata',8);
        $this->assertSame(8,ContentEntry::where('status','draft')->count());
        $this->assertTrue(SeoMetadata::query()->get()->every(
            fn (SeoMetadata $seo)=>filled($seo->seo_title) && filled($seo->meta_description)
                && $seo->canonical_path===null && $seo->robots_index===true
        ));
        $this->artisan('samithai:seed-educational-seo',$this->args($owner,'apply'))
            ->expectsOutput('NEW_SEO_RECORDS_CREATED=0')->assertSuccessful();
        $this->assertDatabaseCount('seo_metadata',8);
    }

    public function test_preexisting_customized_seo_prevents_any_overwrite(): void
    {
        $owner=$this->setupOwnerAndContent();
        $post=ContentEntry::where('kind','post')->firstOrFail();
        $post->seo()->create(['seo_title'=>'Human edited SEO title','robots_index'=>false]);
        $this->artisan('samithai:seed-educational-seo',$this->args($owner,'apply'))
            ->assertFailed();
        $this->assertDatabaseCount('seo_metadata',1);
        $this->assertSame('Human edited SEO title',$post->seo->fresh()->seo_title);
        $this->assertSame(8,ContentEntry::where('status','draft')->count());
    }
}
