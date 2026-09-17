export interface CartSummary { units: number; revision: number }
export interface CartLine {
    id: string; quantity: number; available: boolean; reason: 'unpublished' | 'currency_changed' | null;
    name: string; slug: string | null; is_demo: boolean; image: { url: string; alt: string } | null;
    unit_price_minor: number | null; subtotal_minor: number | null; currency: string | null;
}
export interface Cart {
    lines: CartLine[]; currency: string | null; units: number; subtotal_minor: number; total_minor: number | null;
    has_unavailable: boolean; revision: number; max_quantity: number; max_lines: number;
}
