<?php

declare(strict_types=1);

/**
 * CI-only fixtures. Never load this file on production or an ordinary staging host.
 * The caller must set both GitHub Actions identity and the exact disposable DB name.
 */
if (getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('SAMITH_RECOVERY_FIXTURE') !== 'isolated'
    || getenv('DB_DATABASE') !== 'samith_recovery_source'
    || getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "CI recovery fixture authorization failed.\n");
    exit(2);
}

$base = dirname(__DIR__, 2);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\LegalVersion;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Prompt;
use App\Models\PromptEntitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

if (DB::connection()->getDatabaseName() !== 'samith_recovery_source') {
    fwrite(STDERR, "Unexpected database. Fixtures aborted.\n");
    exit(3);
}

$image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL4NwAAAABJRU5ErkJggg==', true);
if (! is_string($image) || ! Storage::disk('local')->put('media/ci/fixture.png', $image)) {
    throw new RuntimeException('Cannot prepare isolated media fixture.');
}

DB::transaction(static function () use ($image): void {
    $creator = User::create([
        'name' => 'CI Recovery Creator', 'email' => 'ci-recovery-creator@example.test',
        'password' => 'CI-not-a-real-login-secret-1234',
    ]);
    $creator->forceFill(['role' => 'creator'])->save();

    $buyer = User::create([
        'name' => 'CI Recovery Customer', 'email' => 'ci-recovery-customer@example.test',
        'password' => 'CI-not-a-real-login-secret-1234',
    ]);

    $category = Category::create([
        'name' => 'CI Recovery Tools', 'slug' => 'ci-recovery-tools', 'is_active' => true,
    ]);
    $media = MediaAsset::create([
        'user_id' => $creator->id, 'title' => 'CI private media', 'kind' => 'image',
        'mime' => 'image/png', 'file_path' => 'media/ci/fixture.png',
        'bytes' => strlen($image), 'checksum' => hash('sha256', $image),
    ]);
    $prompt = Prompt::create([
        'user_id' => $creator->id, 'category_id' => $category->id,
        'title' => 'CI Recovery Prompt', 'slug' => 'ci-recovery-prompt',
        'summary' => 'CI fixture only', 'content' => 'CI PRIVATE PROMPT CONTENT',
        'price' => 0, 'status' => 'published', 'license_type' => 'regular',
        'published_at' => now(), 'featured_media_id' => $media->id,
    ]);
    DB::table('prompt_saves')->insert([
        'user_id' => $buyer->id, 'prompt_id' => $prompt->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $order = Order::create([
        'user_id' => $buyer->id, 'number' => 'CI-RECOVERY-0001',
        'status' => 'completed', 'currency' => 'USD', 'total' => 0,
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'prompt_id' => $prompt->id,
        'title' => $prompt->title, 'unit_price' => 0, 'license_type' => 'regular',
        'content_snapshot' => $prompt->content,
    ]);
    PromptEntitlement::create([
        'user_id' => $buyer->id, 'prompt_id' => $prompt->id,
        'order_id' => $order->id, 'license_type' => 'regular',
        'content_snapshot' => $prompt->content,
    ]);
    $legal = LegalVersion::create([
        'kind' => 'regular', 'version' => 'ci.1', 'title' => 'CI Non-Legal Test',
        'body_html' => '<p>CI fixture only, not a valid customer license.</p>',
        'status' => 'draft',
    ]);
    DB::table('legal_acceptances')->insert([
        'user_id' => $buyer->id, 'order_id' => $order->id,
        'legal_version_id' => $legal->id, 'accepted_at' => now(),
    ]);
    ContentEntry::create([
        'user_id' => $creator->id, 'kind' => 'article',
        'title' => 'CI Recovery Article', 'slug' => 'ci-recovery-article',
        'body_html' => '<p>CI test page</p>', 'status' => 'draft',
    ]);
    DB::table('audit_events')->insert([
        'user_id' => $creator->id, 'action' => 'ci.recovery.snapshot',
        'subject_type' => 'Prompt', 'subject_id' => $prompt->id, 'created_at' => now(),
    ]);
});

fwrite(STDOUT, "CI_RECOVERY_FIXTURES_CREATED\n");
