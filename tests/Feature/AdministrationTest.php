<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Prompt;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user;
    }

    public function test_only_administrators_manage_roles_and_self_demotion_is_blocked(): void
    {
        $admin=$this->user('admin');
        $customer=$this->user('customer');

        $this->actingAs($customer)->get(route('dashboard.users'))->assertForbidden();
        $this->actingAs($customer)->put(route('dashboard.users.role',$admin),['role'=>'customer'])->assertForbidden();
        $this->actingAs($admin)->put(route('dashboard.users.role',$admin),['role'=>'customer'])->assertSessionHasErrors('role');
        $this->actingAs($admin)->put(route('dashboard.users.role',$customer),['role'=>'creator'])->assertRedirect();
        $this->assertSame('creator',$customer->fresh()->role);
        $this->assertDatabaseHas('audit_events',['action'=>'user.role.update','user_id'=>$admin->id]);
    }

    public function test_appearance_persists_and_changes_the_live_homepage(): void
    {
        $admin=$this->user('admin');
        $customer=$this->user('customer');
        $payload=[
            'site_name'=>'Samith Prompt Studio',
            'site_description'=>'Curated marketplace of reusable prompts',
            'hero_title'=>'Create with better prompts',
            'hero_subtitle'=>'Real prompt tools from creators',
            'primary_color'=>'#B95C26',
            'secondary_color'=>'#195EBC',
            'logo_media_id'=>'',
        ];
        $this->actingAs($customer)->put(route('dashboard.appearance.update'),$payload)->assertForbidden();
        $this->actingAs($admin)->put(route('dashboard.appearance.update'),$payload)->assertRedirect();
        $this->assertSame('Samith Prompt Studio',SiteSetting::text('site_name'));
        $this->get(route('home'))->assertOk()->assertSee('Create with better prompts')->assertSee('#B95C26');
    }

    public function test_uploaded_images_are_private_until_featured_on_published_content(): void
    {
        Storage::fake('local');
        $creator=$this->user('creator');
        $customer=$this->user('customer');
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Vz1NxoAAAAASUVORK5CYII=');

        $this->actingAs($creator)->post(route('media.upload'),[
            'title'=>'Cover image','file'=>UploadedFile::fake()->createWithContent('cover.png',$png),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $asset=MediaAsset::firstOrFail();
        $this->assertSame('image',$asset->kind);
        $this->actingAs($customer)->get(route('media.serve',$asset))->assertForbidden();

        $category=Category::create(['name'=>'Art','slug'=>'art','is_active'=>true]);
        Prompt::create([
            'user_id'=>$creator->id,'category_id'=>$category->id,'title'=>'Art prompt',
            'slug'=>'art-prompt','summary'=>'Color illustration prompt',
            'content'=>'SECRET CONTENT','price'=>0,'status'=>'published','license_type'=>'regular',
            'featured_media_id'=>$asset->id,'published_at'=>now(),
        ]);
        $this->get(route('media.serve',$asset))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->actingAs($creator)->delete(route('media.destroy',$asset))->assertSessionHasErrors('media');
        $this->assertDatabaseHas('media_assets',['id'=>$asset->id]);
    }

    public function test_authentication_records_event_without_password(): void
    {
        $user=User::factory()->create(['password'=>'somepass1234']);
        $this->post(route('login.submit'),['email'=>$user->email,'password'=>'somepass1234'])->assertRedirect();
        $event=AuditEvent::where('action','auth.login')->firstOrFail();
        $this->assertSame($user->id,$event->user_id);
        $this->assertStringNotContainsString('somepass1234',json_encode($event->toArray()));
    }
}
