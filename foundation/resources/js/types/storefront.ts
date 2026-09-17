import type { Specification, Paginator } from './catalog';
export interface Identity { name: string; mark: string; tagline: string; description: string; locale: string; region: string }
export interface PublicTaxonomy { name: string; slug: string; description?: string | null; parent_slug?: string | null }
export interface PublicImage { id: number; url: string; alt: string; position: number; is_primary: boolean }
export interface PublicProduct {
    name: string; sku: string; slug: string; short_description: string; description: string;
    warranty: string | null; specifications: Specification[]; price_minor: number;
    previous_price_minor: number | null; currency: string; featured: boolean; is_demo: boolean;
    meta_title: string | null; meta_description: string | null; category: PublicTaxonomy;
    brand: PublicTaxonomy; images: PublicImage[];
}
export interface Seo { title: string; description: string; url: string; image: string | null; robots: string; site_name: string; locale: string }
export type Filters = Partial<Record<'q' | 'category' | 'brand' | 'currency' | 'min' | 'max' | 'sort' | 'editorial' | 'featured' | 'offers' | 'page', string>>;
export type ProductPage = Paginator<PublicProduct> & { last_page: number; from: number | null; to: number | null };
export const catalogUrl = (filters: Filters = {}) => `/catalog${Object.keys(filters).length ? `?${new URLSearchParams(filters as Record<string, string>).toString()}` : ''}`;
