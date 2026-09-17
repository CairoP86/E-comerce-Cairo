export type Role = 'customer' | 'operator' | 'admin';
export interface AuthUser { id: number; name: string; email: string; role: Role; email_verified_at: string | null }
export interface SharedProps { auth: { user: AuthUser | null }; status: string | null; [key: string]: unknown }
