export interface Specification { key: string; label: string; value: string; unit: string | null; group: string | null }
export interface CatalogImage { id: number; alt: string; position: number; is_primary: boolean; url?: string }
export type CatalogStatus = 'draft' | 'published' | 'archived';
export interface Taxonomy { id: number; name: string; slug: string; description: string | null; status: CatalogStatus; parent_id?: number | null }
export interface Product { id: number; name: string; sku: string; slug: string; category_id: number; brand_id: number; category?: Taxonomy; brand?: Taxonomy; short_description: string; description: string; warranty: string | null; specifications: Specification[]; price_minor: number; previous_price_minor: number | null; currency: string; status: CatalogStatus; featured: boolean; is_demo: boolean; meta_title: string | null; meta_description: string | null; images: CatalogImage[] }
export interface Paginator<T> { data: T[]; current_page: number; total: number; prev_page_url: string | null; next_page_url: string | null }
export const money = (minor: number, currency: string) => new Intl.NumberFormat('es-CR', { style: 'currency', currency }).format(minor / 100);
export const statusLabel = (status: CatalogStatus) => ({ draft: 'Borrador', published: 'Publicado', archived: 'Archivado' }[status]);
