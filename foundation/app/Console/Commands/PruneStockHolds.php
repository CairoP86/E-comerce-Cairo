<?php

namespace App\Console\Commands;

use App\Services\StockHolds;
use Illuminate\Console\Command;

class PruneStockHolds extends Command
{
    protected $signature = 'holds:prune';

    protected $description = 'Delete expired local cart holds. Expired holds already stopped counting; this only cleans rows.';

    public function handle(StockHolds $holds): int
    {
        $this->info('Expired cart holds removed: '.$holds->prune());

        return self::SUCCESS;
    }
}
