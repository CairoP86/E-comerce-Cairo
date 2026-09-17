export interface OrderItem { name: string; sku: string; quantity: number; unit_price_minor: number; subtotal_minor: number; currency: string; is_demo: boolean }
export interface Review { items: OrderItem[]; currency: string; total_minor: number; token: string }
export interface Territory { province_code: string; province: string; canton_code: string; canton: string; code: string; name: string }
export interface Order { number: string; created_at: string; status: string; status_label: string; buyer: { first_name: string; last_name: string; email: string; phone: string }; address: { country_code: string; province: string; canton: string; district: string; exact_address: string; additional: string | null }; items: OrderItem[]; currency: string; subtotal_minor: number; total_minor: number }
