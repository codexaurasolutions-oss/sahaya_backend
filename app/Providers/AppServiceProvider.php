<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Setting;
use App\Models\UserWorkInfo;
use View;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        Paginator::useBootstrap();

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'user' => \App\Models\User::class,
            'vendor' => \App\Models\User::class,
        ]);
        
        // ✅ Auto-generate embedding when staff profile is saved
        UserWorkInfo::saved(function (UserWorkInfo $workInfo) {
            try {
                if (empty($workInfo->embedding)) {
                    $user = $workInfo->user;
                    if (!$user || $user->user_role_id != 2) return;

                    $service = new \App\Services\EmbeddingService();
                    $text = $service->buildStaffText($user);
                    if (empty(trim($text))) return;

                    $embedding = $service->generateEmbedding($text);
                    if ($embedding) {
                        $workInfo->updateQuietly(['embedding' => json_encode($embedding)]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Auto-embedding failed for user ' . $workInfo->user_id, [
                    'error' => $e->getMessage()
                ]);
            }
        });

        // ✅ Prevent DB queries during CLI / Composer / Build
        if (app()->runningInConsole()) {
            return;
        }
        
        if (!Cache::has('attendance_last_run')) {
            Artisan::call('attendance:auto-mark');
            Cache::put('attendance_last_run', now(), 60); // run once per minute
        }


        $youtube   = Setting::where('key','Social.youtube')->first();
        $facebook  = Setting::where('key','Social.facebook')->first();
        $twitter   = Setting::where('key','Social.twitter')->first();
        $linkedin  = Setting::where('key','Social.linkedin')->first();
        $copyright = Setting::where('key','Site.right')->first();

        View::share(compact(
            'youtube',
            'facebook',
            'twitter',
            'linkedin',
            'copyright'
        ));
    }
}
