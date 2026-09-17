<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class VerifyInfrastructure implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $token) {}

    public function handle(): void
    {
        Cache::store('redis')->put('foundation:queue:'.$this->token, 'processed', 120);
    }
}
