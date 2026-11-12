<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\SeoMetadata;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
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

    public function test_six_article_review_report_checks_imported_text_and_featured_social_fallback_read_only(): void
    {
        $owner=$this->setupOwnerAndContent();
        $this->artisan('samithai:seed-educational-seo',$this->args($owner,'apply'))->assertSuccessful();
        Storage::fake('local');
        $images=[
            '20-practical-ai-prompts-for-small-businesses'=>'01_small-business-ai-prompts',
            'how-teachers-can-use-ai-to-prepare-lesson-plans'=>'02_teachers-ai-lesson-plans',
            'how-to-create-a-professional-website-using-ai'=>'03_ai-website-from-brief-to-launch',
            '15-ai-prompts-for-social-media-marketing'=>'04_social-media-ai-prompts',
            'how-to-test-and-improve-an-ai-prompt'=>'05_ai-prompt-quality-checklist',
            'ai-prompt-engineering-vs-ai-agents'=>'06_prompt-engineering-vs-ai-agents',
        ];
        foreach ($images as $slug=>$title) {
            $path='media/'.$title.'.webp';
            Storage::disk('local')->put($path,'synthetic-webp-fixture');
            $asset=MediaAsset::query()->create([
                'user_id'=>$owner->id,'title'=>$title,'kind'=>'image',
                'mime'=>'image/webp','file_path'=>$path,'bytes'=>22,
            ]);
            ContentEntry::query()->where('kind','post')->where('slug',$slug)
                ->update(['featured_media_id'=>$asset->id]);
        }
        $before=ContentEntry::query()->orderBy('id')
            ->get(['id','status','featured_media_id','editorial_status'])->toJson();
        $this->assertSame(0,Artisan::call('samithai:review-six-educational-drafts',[
            '--json'=>true,'--strict'=>true,
        ]));
        $report=json_decode(Artisan::output(),true,flags:JSON_THROW_ON_ERROR);
        $this->assertSame('AUTOMATIC_CHECKS_PASS',$report['status']);
        $this->assertSame(6,$report['article_count']);
        foreach ($report['articles'] as $row) {
            $this->assertSame('draft',$row['public_status']);
            $this->assertTrue($row['matches_imported_source']);
            $this->assertTrue($row['featured_media_verified']);
            $this->assertTrue($row['seo_saved']);
            $this->assertSame('assigned_featured_fallback',$row['social_image_strategy']);
            $this->assertFalse($row['current_human_approval']);
            $this->assertTrue($row['requires_real_human_review']);
        }
        $this->assertSame($before,ContentEntry::query()->orderBy('id')
            ->get(['id','status','featured_media_id','editorial_status'])->toJson());
        $this->assertDatabaseCount('seo_metadata',8);
    }


}
