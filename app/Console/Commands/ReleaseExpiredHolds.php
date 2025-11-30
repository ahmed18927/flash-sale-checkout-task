<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hold;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;

class ReleaseExpiredHolds extends Command
{
    protected $signature = 'holds:release-expired';
    protected $description = 'Release holds that expired but still reserved';

    public function handle()
    {
        $now = now();

        Hold::where('status', 'reserved')
            ->where('expires_at', '<', $now)
            ->chunkById(100, function ($holds) {
                foreach ($holds as $hold) {
                    $hold->status = 'expired';
                    $hold->save();
                }
            });

        $this->info('Expired holds released.');
    }
}
