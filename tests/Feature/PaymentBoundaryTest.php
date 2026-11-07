<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\User;
use App\Payments\VerifiedPaymentFulfillment;
use App\Payments\VerifiedProviderReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(): Order
    {
        $creator=User::factory()->create();
        $customer=User::factory()->create();
        $category=Category::create(['name'=>'Writing','slug'=>'writing','is_active'=>true]);
        $prompt=Prompt::create([
            'user_id'=>$creator->id,'category_id'=>$category->id,'title'=>'Writer',
            'slug'=>'writer','summary'=>'Writer helper','content'=>'LICENSED_SNAPSHOT',
            'price'=>9.99,'status'=>'published','license_type'=>'regular',
        ]);
        $order=Order::create([
            'user_id'=>$customer->id,'number'=>'SA-PENDING-123','status'=>'pending',
            'currency'=>'USD','total'=>'9.99',
        ]);
        $order->items()->create([
            'prompt_id'=>$prompt->id,'title'=>$prompt->title,'unit_price'=>'9.99',
            'license_type'=>'regular','content_snapshot'=>'LICENSED_SNAPSHOT',
        ]);
        return $order;
    }

    public function test_default_disabled_gateway_rejects_even_a_matching_receipt(): void
    {
        $order=$this->pendingOrder();
        $this->expectException(LogicException::class);
        (new VerifiedPaymentFulfillment)->fulfill($order,
            new VerifiedProviderReceipt('sandbox','reference-123','USD','9.99',$order->number));
    }

    public function test_verified_fulfillment_checks_exact_total_and_is_idempotent_only_in_test_configuration(): void
    {
        config(['payments.enabled'=>true,'payments.provider'=>'test-only','payments.supported_providers'=>['test-only']]);
        $order=$this->pendingOrder();
        $service=new VerifiedPaymentFulfillment;
        try {
            $service->fulfill($order,new VerifiedProviderReceipt('test-only','bad','USD','0.01',$order->number));
            $this->fail('Wrong amount was accepted');
        } catch (LogicException $e) {
            $this->assertStringContainsString('does not match',$e->getMessage());
        }
        $this->assertDatabaseMissing('payment_transactions',['order_id'=>$order->id]);
        $this->assertDatabaseMissing('prompt_entitlements',['user_id'=>$order->user_id]);

        $receipt=new VerifiedProviderReceipt('test-only','exact-reference','USD','9.99',$order->number);
        $service->fulfill($order,$receipt);
        $service->fulfill($order,$receipt);
        $this->assertDatabaseCount('payment_transactions',1);
        $this->assertDatabaseCount('prompt_entitlements',1);
        $this->assertSame('completed',$order->fresh()->status);
    }
}
