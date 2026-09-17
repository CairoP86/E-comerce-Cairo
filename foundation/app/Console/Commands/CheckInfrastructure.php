<?php

namespace App\Console\Commands;

use App\Jobs\VerifyInfrastructure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class CheckInfrastructure extends Command
{
    protected $signature = 'foundation:check';

    protected $description = 'Verify MySQL, Redis cache, and a dedicated Redis queue round trip';

    public function handle(): int
    {
        $token = (string) Str::uuid();
        $key = 'foundation:check:'.$token;

        try {
            if (DB::connection()->getDriverName() !== 'mysql') {
                throw new \RuntimeException('MySQL required');
            }
            DB::select('SELECT 1');
            $this->info('PASS MySQL connection');
            Redis::connection()->ping();
            Cache::store('redis')->put($key, $token, 60);
            if (Cache::store('redis')->get($key) !== $token) {
                throw new \RuntimeException('Cache round trip failed');
            }
            $this->info('PASS Redis cache write/read');
            $queue = 'foundation-check-'.$token;
            VerifyInfrastructure::dispatch($token)->onConnection('redis')->onQueue($queue);
            $result = $this->call('queue:work', [
                'connection' => 'redis', '--queue' => $queue,
                '--once' => true, '--tries' => 1, '--timeout' => 15,
            ]);
            if ($result !== 0 || Cache::store('redis')->get('foundation:queue:'.$token) !== 'processed') {
                throw new \RuntimeException('Queue round trip failed');
            }
            $this->info('PASS Redis queue dispatch/process');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Do not print connection strings, credentials, or raw provider responses.
            $this->error('Infrastructure check failed: '.$exception::class.'. Verify local service configuration.');

            return self::FAILURE;
        } finally {
            try {
                Cache::store('redis')->forget($key);
                Cache::store('redis')->forget('foundation:queue:'.$token);
            } catch (Throwable) {
                // Preserve the original diagnostic result if Redis is unavailable.
            }
        }
    }
}
