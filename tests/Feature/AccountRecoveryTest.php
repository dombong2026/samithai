<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_explicitly_refuses_to_claim_email_delivery_without_provider(): void
    {
        config(['samith.email_delivery_enabled'=>false]);
        $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'),['email'=>'someone@example.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_reset_link_is_non_enumerating_and_reset_rotates_credentials(): void
    {
        config(['samith.email_delivery_enabled'=>true]);
        Notification::fake();
        $user=User::factory()->create(['password'=>'OldPassword12345']);

        $this->post(route('password.email'),['email'=>'unknown@example.test'])
            ->assertSessionHas('success');
        $this->post(route('password.email'),['email'=>$user->email])
            ->assertSessionHas('success');

        $token=null;
        Notification::assertSentTo($user,ResetPassword::class,function ($notice) use (&$token) {
            $token=$notice->token;
            return true;
        });
        $this->assertNotNull($token);

        $this->post(route('password.update'),[
            'email'=>$user->email,
            'token'=>$token,
            'password'=>'BrandNewPassword123',
            'password_confirmation'=>'BrandNewPassword123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('BrandNewPassword123',$user->fresh()->password));
        $this->assertDatabaseHas('audit_events',['action'=>'auth.password.reset','subject_id'=>$user->id]);

        $this->post(route('password.update'),[
            'email'=>$user->email,
            'token'=>$token,
            'password'=>'AnotherPassword123',
            'password_confirmation'=>'AnotherPassword123',
        ])->assertSessionHasErrors('email');
    }

    public function test_signed_verification_links_and_optional_enforcement(): void
    {
        config(['samith.email_delivery_enabled'=>true,'samith.require_verified_email'=>true]);
        Notification::fake();
        $user=User::factory()->create();
        $user->forceFill(['email_verified_at'=>null])->save();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($user,VerifyEmail::class);

        $this->actingAs($user)->post(route('account.creator'))
            ->assertRedirect(route('verification.notice'));

        $link=URL::temporarySignedRoute('verification.verify',now()->addMinutes(30),[
            'id'=>$user->getKey(),
            'hash'=>sha1($user->getEmailForVerification()),
        ]);
        $this->actingAs($user)->get($link)->assertRedirect(route('account'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->actingAs($user)->post(route('account.creator'))->assertRedirect(route('account.prompts'));
    }

    public function test_invalid_signed_verification_is_rejected(): void
    {
        $user=User::factory()->create();
        $user->forceFill(['email_verified_at'=>null])->save();
        $this->actingAs($user)->get('/email/verify/'.$user->id.'/invalid?signature=wrong')->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
