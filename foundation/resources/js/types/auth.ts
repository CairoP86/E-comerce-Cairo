export type Role = 'customer' | 'operator' | 'admin';
export interface AuthUser { id: number; name: string; email: string; role: Role; email_verified_at: string | null }
/** Sidebar counters; null outside the private area and for customers. */
export interface OperatorTurn { orders: number; offers: number }
export interface SharedProps { auth: { user: AuthUser | null }; status: string | null; operatorTurn?: OperatorTurn | null; [key: string]: unknown }
