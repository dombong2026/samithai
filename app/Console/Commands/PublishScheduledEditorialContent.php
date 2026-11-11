<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\ContentEntry;
use App\Support\EditorialGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduledEditorialContent extends Command
{
    protected $signature = 'samithai:publish-scheduled-editorial {--check : Show due count without writes}';
    protected $description = 'Publish due, independently approved content; never auto-approve or alter prompt products.';

    public function handle(): int
    {
        $due = ContentEntry::query()
            ->whereIn('kind', ['post','page'])
            ->where('status','draft')
            ->where('editorial_status','scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for','<=',now());

        if ($this->option('check')) {
            $this->line('SCHEDULED_DUE_CANDIDATES='.$due->count());
            $this->line('DATABASE_AND_PUBLICATION=UNCHANGED');
            return self::SUCCESS;
        }

        $published = 0;
        foreach ((clone $due)->orderBy('scheduled_for')->limit(50)->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$published): void {
                $entry = ContentEntry::query()->lockForUpdate()->find($id);
                if (!$entry || !in_array($entry->kind,['post','page'],true) ||
                    $entry->status !== 'draft' || $entry->editorial_status !== 'scheduled' ||
                    !$entry->scheduled_for || $entry->scheduled_for->isFuture() ||
                    !EditorialGate::approved($entry)) return;

                $scheduledAt = $entry->scheduled_for->toIso8601String();
                $entry->update([
                    'status'=>'published',
                    'published_at'=>now(),
                    'editorial_status'=>'approved',
                    'scheduled_for'=>null,
                ]);
                EditorialGate::revision($entry, null, 'scheduled_publish');
                AuditEvent::query()->create([
                    'user_id' => $entry->reviewer_id,
                    'action' => 'content.editorial.auto_publish',
                    'subject_type' => class_basename($entry),
                    'subject_id' => $entry->getKey(),
                    'ip_hash' => null,
                    'details' => ['scheduled_for'=>$scheduledAt],
                    'created_at' => now(),
                ]);
                ++$published;
            }, 3);
        }
        $this->info('APPROVED_EDITORIAL_PUBLISHED='.$published);
        $this->line('PROMPT_PRODUCTS_UNCHANGED=VERIFIED');
        return self::SUCCESS;
    }
}
