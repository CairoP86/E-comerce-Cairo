<?php

namespace App\Support;

use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\LostConnectionDetector;
use Illuminate\Database\QueryException;

/**
 * Whether a database failure is worth retrying. Transient failures are about the connection or
 * contention, never about the query: the server is down or restarting, a lock timed out, a deadlock
 * was broken. Everything else, a missing table included, fails the same way on every retry.
 *
 * Laravel's detectors compare English driver messages, but connection errors come from the operating
 * system and arrive localised ("…el equipo de destino denegó…" on a Spanish Windows), so the driver
 * code, which does not depend on the language, decides first.
 */
final readonly class DatabaseFailure
{
    /** MySQL driver codes that say nothing about the query itself. */
    private const TRANSIENT_CODES = [
        1040, // too many connections
        1053, // server shutdown in progress
        1205, // lock wait timeout
        1213, // deadlock
        2002, // cannot connect (refused, socket)
        2003, // cannot connect (host)
        2006, // server has gone away
        2013, // lost connection during query
    ];

    public function __construct(public bool $transient, public ?string $sqlstate, public ?int $driverCode) {}

    public static function of(QueryException $e): self
    {
        $sqlstate = $e->errorInfo[0] ?? self::match('/SQLSTATE\[([0-9A-Z]{5})\]/', $e->getMessage());
        $code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : self::match('/SQLSTATE\[[0-9A-Z]{5}\] \[(\d+)\]/', $e->getMessage());
        $code = $code === null ? null : (int) $code;

        $transient = in_array($code, self::TRANSIENT_CODES, true)
            // SQLSTATE class 08 is connection exceptions; 40001 is a serialization failure.
            || ($sqlstate !== null && (str_starts_with($sqlstate, '08') || $sqlstate === '40001'))
            || (new LostConnectionDetector)->causedByLostConnection($e)
            || (new ConcurrencyErrorDetector)->causedByConcurrencyError($e);

        return new self($transient, $sqlstate, $code);
    }

    private static function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $found) ? $found[1] : null;
    }

    /** Only codes: never the SQL, its bindings or the driver message, which can carry order data. */
    public function logContext(): array
    {
        return ['sqlstate' => $this->sqlstate, 'driver_code' => $this->driverCode, 'transient' => $this->transient];
    }
}
