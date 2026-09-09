<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MonitorHealthCommand extends Command
{
    protected $signature = 'monitor:health {--once : Run a single check cycle and exit}';

    protected $description = 'Probe configured health endpoints';

    public function handle(): int
    {
        $runOnce = (bool) $this->option('once');
        $intervalSeconds = max(5, (int) config('monitoring.interval_seconds', 60));
        $targets = (array) config('monitoring.targets', []);
        $exitCode = self::SUCCESS;

        do {
            $checked = 0;
            $healthy = 0;

            foreach ($targets as $key => $url) {
                $checked++;
                $started = microtime(true);

                try {
                    $response = Http::timeout(10)->get($url);
                    $latencyMs = (int) round((microtime(true) - $started) * 1000);
                    $isHealthy = $response->successful();

                    if ($isHealthy) {
                        $healthy++;
                    } else {
                        $exitCode = self::FAILURE;
                    }

                    $this->line(sprintf(
                        '  %-12s %s (%d ms) — HTTP %s',
                        $key,
                        $isHealthy ? 'OK' : 'FAIL',
                        $latencyMs,
                        $response->status(),
                    ));
                } catch (\Throwable $exception) {
                    $exitCode = self::FAILURE;
                    $latencyMs = (int) round((microtime(true) - $started) * 1000);

                    $this->line(sprintf(
                        '  %-12s FAIL (%d ms) — %s',
                        $key,
                        $latencyMs,
                        $exception->getMessage(),
                    ));
                }
            }

            $this->info(sprintf(
                '[%s] Health checks: %d total, %d healthy, %d unhealthy',
                now()->toDateTimeString(),
                $checked,
                $healthy,
                $checked - $healthy,
            ));

            if ($runOnce) {
                break;
            }

            sleep($intervalSeconds);
        } while (true);

        return $exitCode;
    }
}
