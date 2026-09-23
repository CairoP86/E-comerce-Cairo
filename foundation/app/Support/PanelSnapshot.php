<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The context around "your turn": what the catalogue looks like, how orders moved day by day, and
 * what happened recently. Everything comes from data the admin already keeps; nothing is estimated.
 *
 * Days are Costa Rica days. An order placed at 23:30 belongs to that evening, not to the next day
 * in UTC, so every bucket is built from the local date of the timestamp.
 */
class PanelSnapshot
{
    public const RANGES = [14, 30];

    private const TIMEZONE = 'America/Costa_Rica';

    /** @return array{catalogue: array, suppliers: array, series: array, paid: array, activity: array, days: int} */
    public function for(int $days): array
    {
        $days = in_array($days, self::RANGES, true) ? $days : self::RANGES[0];
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $from = $today->subDays($days - 1);

        return [
            'days' => $days,
            'catalogue' => $this->catalogue(),
            'suppliers' => $this->suppliers(),
            'series' => $this->series($from, $today, $days),
            'paid' => $this->paid($from),
            'activity' => $this->activity(),
        ];
    }

    private function catalogue(): array
    {
        $counts = Product::query()->selectRaw('status, is_demo, count(*) as total')->groupBy('status', 'is_demo')->get();
        $real = fn (string $status) => (int) $counts->where('is_demo', false)->where('status', $status)->sum('total');

        return [
            'published' => $real('published'), 'draft' => $real('draft'), 'archived' => $real('archived'),
            // Demonstration products are hidden from the lists, so the panel says how many are held back.
            'demo' => (int) $counts->where('is_demo', true)->sum('total'),
        ];
    }

    private function suppliers(): array
    {
        $total = Supplier::count();

        return ['total' => $total, 'active' => Supplier::where('active', true)->count()];
    }

    /** One bucket per day, zeros included: an empty day is information too. */
    private function series(CarbonImmutable $from, CarbonImmutable $today, int $days): array
    {
        $moves = DB::table('order_status_history')->where('created_at', '>=', $from->utc())
            ->get(['to_status', 'created_at'])
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->created_at, 'UTC')->setTimezone(self::TIMEZONE)->toDateString());

        $series = [];
        for ($day = $from; $day <= $today; $day = $day->addDay()) {
            $rows = $moves->get($day->toDateString(), collect());
            $series[] = [
                'date' => $day->toDateString(),
                'label' => $day->locale('es')->isoFormat('D MMM'),
                'created' => $rows->where('to_status', OrderStatus::PendingPayment->value)->count(),
                'paid' => $rows->where('to_status', OrderStatus::Paid->value)->count(),
            ];
        }

        return array_slice($series, -$days);
    }

    /** What was confirmed inside the range, by currency: the store may not always charge in colones. */
    private function paid(CarbonImmutable $from): array
    {
        $ids = DB::table('order_status_history')->where('to_status', OrderStatus::Paid->value)
            ->where('created_at', '>=', $from->utc())->pluck('order_id');
        $totals = Order::whereIn('id', $ids)->selectRaw('currency, sum(total_minor) as total_minor, count(*) as orders')
            ->groupBy('currency')->orderBy('currency')->get();

        return [
            'orders' => (int) $totals->sum('orders'),
            'totals' => $totals->map(fn ($row) => ['currency' => $row->currency, 'total_minor' => (int) $row->total_minor])->values()->all(),
        ];
    }

    /**
     * The last six entries of the activity log, named like the audit screen names them. Opening a
     * record is logged for the audit trail but left out here: a browsing spree would crowd out the
     * six lines that say what actually changed. Auditoría still shows every one of them.
     */
    private function activity(): array
    {
        $entries = AuditLog::query()->whereNotIn('event', ['order.viewed'])->latest('id')->limit(6)->get();
        $people = User::whereIn('id', $entries->pluck('actor_id')->filter()->unique())->get(['id', 'name', 'email'])->keyBy('id');

        return $entries->map(fn (AuditLog $entry) => [
            ...$entry->only(['id', 'event', 'source', 'metadata', 'created_at']),
            'actor' => $people->get($entry->actor_id)?->only(['id', 'name', 'email']),
        ])->all();
    }
}
