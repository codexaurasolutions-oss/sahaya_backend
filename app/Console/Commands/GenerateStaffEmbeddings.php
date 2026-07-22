<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class GenerateStaffEmbeddings extends Command
{
    protected $signature = 'embeddings:generate-staff
                            {--user= : Generate embedding for a specific user ID only}
                            {--force : Regenerate even if embedding already exists}';

    protected $description = 'Generate OpenAI embeddings for all staff profiles for semantic search';

    public function handle(): int
    {
        $embeddingService = new EmbeddingService();
        $userId = $this->option('user');
        $force = $this->option('force');

        $query = User::with(['userWorkInfo', 'addresses'])
            ->where('user_role_id', 2) // Staff
            ->where('is_job_seeking', 1);

        if ($userId) {
            $query->where('id', $userId);
        }

        $staff = $query->get();

        if ($staff->isEmpty()) {
            $this->info('No staff found to process.');
            return Command::SUCCESS;
        }

        $total = $staff->count();
        $this->info("Processing {$total} staff members...");

        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $batchSize = 50;
        $batch = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($staff as $member) {
            // Skip if already has embedding (unless --force)
            if (!$force && !empty($member->userWorkInfo?->embedding)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $text = $embeddingService->buildStaffText($member);

            if (empty(trim($text))) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $batch[] = [
                'user_id' => $member->id,
                'text' => $text,
            ];

            // Process batch when it reaches batch size
            if (count($batch) >= $batchSize) {
                $failed += $this->processBatch($batch, $embeddingService);
                $processed += count($batch);
                $batch = [];
            }

            $bar->advance();
        }

        // Process remaining batch
        if (!empty($batch)) {
            $failed += $this->processBatch($batch, $embeddingService);
            $processed += count($batch);
        }

        $bar->finish();
        $this->newLine();

        $this->info("Done! Processed: {$processed}, Skipped: {$skipped}, Failed: {$failed}");

        if ($processed > 0) {
            $this->info("Embeddings are now stored in user_work_infos.embedding column.");
        }

        return Command::SUCCESS;
    }

    private function processBatch(array $batch, EmbeddingService $embeddingService): int
    {
        $texts = array_column($batch, 'text');
        $userIds = array_column($batch, 'user_id');
        $failedCount = 0;

        $embeddings = $embeddingService->generateBatchEmbeddings($texts);

        foreach ($batch as $i => $item) {
            $embedding = $embeddings[$i] ?? null;

            if ($embedding) {
                \App\Models\UserWorkInfo::where('user_id', $item['user_id'])
                    ->update(['embedding' => json_encode($embedding)]);
            } else {
                $this->warn("Failed to generate embedding for user ID: {$item['user_id']}");
                $failedCount++;
            }
        }

        return $failedCount;
    }
}
