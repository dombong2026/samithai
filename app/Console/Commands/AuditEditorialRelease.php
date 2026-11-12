<?php

namespace App\Console\Commands;

use App\Models\ContentEntry;
use App\Models\Prompt;
use App\Support\EditorialGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class AuditEditorialRelease extends Command
{
    protected $signature = 'samithai:editorial-release-audit
        {--json : Output a credential-free JSON report}
        {--strict : Fail on editorial integrity violations}
        {--expect-starters : Also require the six known pending drafts and at least 30 prompt products}';

    protected $description = 'Read-only editorial integrity audit for an isolated migration rehearsal or approved server.';

    private const PENDING_DRAFT_SLUGS = [
        '20-practical-ai-prompts-for-small-businesses',
        'how-teachers-can-use-ai-to-prepare-lesson-plans',
        'how-to-create-a-professional-website-using-ai',
        '15-ai-prompts-for-social-media-marketing',
        'how-to-test-and-improve-an-ai-prompt',
        'ai-prompt-engineering-vs-ai-agents',
    ];

    public function handle(): int
    {
        $required = ['content_entries', 'content_editorial_revisions', 'seo_metadata', 'media_assets', 'prompts'];
        $missingTables = array_values(array_filter($required, static fn (string $table): bool => !Schema::hasTable($table)));

        if ($missingTables !== []) {
            $this->error('REQUIRED_MIGRATIONS_MISSING='.implode(',', $missingTables));
            $this->line('DATABASE_AND_PUBLICATION=UNCHANGED');
            return self::FAILURE;
        }

        $posts = ContentEntry::query()->where('kind', 'post');
        $statusCounts = [
            'posts' => (clone $posts)->count(),
            'draft_posts' => (clone $posts)->where('status', 'draft')->count(),
            'published_posts' => (clone $posts)->where('status', 'published')->count(),
            'in_review' => (clone $posts)->where('editorial_status', 'in_review')->count(),
            'approved' => (clone $posts)->where('editorial_status', 'approved')->count(),
            'scheduled' => (clone $posts)->where('editorial_status', 'scheduled')->count(),
            'prompt_products' => Prompt::query()->count(),
        ];

        $invalidApprovals = 0;
        $invalidSchedules = 0;
        ContentEntry::query()->whereIn('kind', ['post', 'page'])
            ->whereIn('editorial_status', ['approved', 'scheduled'])
            ->with('seo')->orderBy('id')->chunkById(200, function ($entries) use (&$invalidApprovals, &$invalidSchedules): void {
                foreach ($entries as $entry) {
                    if (!EditorialGate::approved($entry)) {
                        ++$invalidApprovals;
                    }
                    if ($entry->editorial_status === 'scheduled'
                        && ($entry->status !== 'draft' || $entry->scheduled_for === null)) {
                        ++$invalidSchedules;
                    }
                }
            });

        $dueCandidates = ContentEntry::query()->whereIn('kind', ['post', 'page'])
            ->where('status', 'draft')->where('editorial_status', 'scheduled')
            ->whereNotNull('scheduled_for')->where('scheduled_for', '<=', now())->count();

        $pending = ContentEntry::query()->where('kind', 'post')
            ->whereIn('slug', self::PENDING_DRAFT_SLUGS)
            ->pluck('status', 'slug')->all();
        $missingDrafts = count(array_diff(self::PENDING_DRAFT_SLUGS, array_keys($pending)));
        $publishedPendingDrafts = count(array_filter($pending, static fn ($status): bool => $status !== 'draft'));
        $baselineIssue = $this->option('expect-starters')
            && ($missingDrafts > 0 || $publishedPendingDrafts > 0 || $statusCounts['prompt_products'] < 30);

        $integrityIssue = $invalidApprovals > 0 || $invalidSchedules > 0;
        $report = [
            'status' => $integrityIssue || $baselineIssue ? 'REVIEW_REQUIRED' : 'PASS',
            'read_only' => true,
            'counts' => $statusCounts,
            'invalid_current_approvals' => $invalidApprovals,
            'invalid_schedule_states' => $invalidSchedules,
            'due_schedule_candidates' => $dueCandidates,
            'starter_baseline_checked' => (bool) $this->option('expect-starters'),
            'starter_drafts_missing' => $missingDrafts,
            'starter_drafts_not_draft' => $publishedPendingDrafts,
            'database_and_publication' => 'UNCHANGED',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            foreach ($report as $key => $value) {
                $this->line(strtoupper($key).'='.(is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    : (is_bool($value) ? ($value ? 'YES' : 'NO') : (string) $value)));
            }
            $this->warn('A read-only database audit cannot prove running cron, image rights, real-browser behavior or production readiness.');
        }

        return $this->option('strict') && ($integrityIssue || $baselineIssue) ? self::FAILURE : self::SUCCESS;
    }
}
