/** "hace 5 días", "hace 2 h", "ayer": how long something has been waiting, in Spanish. */
export function ago(iso: string): string {
    const seconds = (Date.now() - Date.parse(iso)) / 1000;
    const rtf = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
    if (seconds < 3600) return rtf.format(-Math.max(1, Math.round(seconds / 60)), 'minute');
    if (seconds < 86400) return rtf.format(-Math.round(seconds / 3600), 'hour');
    return rtf.format(-Math.round(seconds / 86400), 'day');
}

/** Date and time as the operator reads them, in Costa Rica time. */
export function dateTime(iso: string): string {
    return new Intl.DateTimeFormat('es-CR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Costa_Rica' }).format(new Date(iso));
}
