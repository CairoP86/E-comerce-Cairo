// Offer freshness for the admin only (App\Availability\OfferFreshness). Never sent to public pages.
export type FreshnessLevel = 'fresh' | 'expiring' | 'expired' | 'invalid' | 'none';
export interface Freshness {
    level: FreshnessLevel;
    state: 'available' | 'unavailable' | 'stale' | 'unknown';
    quantity: number | null;
    observed_at: string | null;
    expires_at: string | null;
    remaining_minutes: number | null;
}
export interface FreshnessSummary { expired: number; expiring: number; invalid: number }

/** 4320 minutes read as "3 d", 95 as "1 h 35 min": precise when little time is left. */
export function duration(minutes: number): string {
    const m = Math.abs(minutes);
    if (m < 1) return 'menos de 1 min';
    if (m < 60) return `${m} min`;
    const h = Math.floor(m / 60);
    if (h < 6) return m % 60 ? `${h} h ${m % 60} min` : `${h} h`;
    if (h < 48) return `${h} h`;
    const d = Math.floor(h / 24);
    return h % 24 ? `${d} d ${h % 24} h` : `${d} d`;
}

export function freshnessLabel(f: Freshness): string {
    switch (f.level) {
        case 'fresh': return `Vigente · vence en ${duration(f.remaining_minutes ?? 0)}`;
        case 'expiring': return `Por vencer · quedan ${duration(f.remaining_minutes ?? 0)}`;
        case 'expired': return `Vencida hace ${duration(f.remaining_minutes ?? 0)}`;
        case 'invalid': return 'Sin dato válido';
        default: return 'Sin oferta preferida';
    }
}

/** What the observation says about stock, independent of how fresh it is. */
export function stockLabel(f: Freshness): string {
    if (f.state === 'available') return f.quantity === 1 ? '1 unidad' : `${f.quantity} unidades`;
    if (f.state === 'unavailable') return 'Agotado';
    return '';
}

/** Levels the operator has to act on before the product disappears, or to bring it back. */
export const needsAttention = (f?: Freshness): boolean => !!f && f.level !== 'fresh';
