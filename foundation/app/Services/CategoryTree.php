<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Categories for the admin table: in tree order with their depth, product counts for the category
 * itself and for its whole branch, and the pricing rule that applies to it.
 *
 * The rule follows the same nearest-ancestor walk as CommercialPricing, so the table and the price
 * suggestion always agree; a test cross-checks every category. Every walk carries a visited set, so
 * a broken parent cycle degrades to extra roots instead of hanging the page.
 */
class CategoryTree
{
    private Collection $categories;

    private Collection $children;

    private Collection $rules;

    private Collection $direct;

    private array $rows = [];

    private array $visited = [];

    public function entries(): array
    {
        $this->categories = Category::query()->orderBy('name')->get()->keyBy('id');
        // Siblings keep the name order of the query above.
        $this->children = $this->categories->groupBy(fn (Category $c) => $this->categories->has($c->parent_id) ? $c->parent_id : 0);
        $this->rules = DB::table('category_pricing_rules')->join('pricing_rules', 'pricing_rules.id', '=', 'category_pricing_rules.pricing_rule_id')
            ->get(['category_pricing_rules.category_id', 'pricing_rules.name', 'pricing_rules.multiplier_units'])->keyBy('category_id');
        $this->direct = Product::query()->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id');

        foreach ($this->children->get(0, collect()) as $root) {
            $this->visit($root, 0);
        }
        // Anything a cycle kept unreachable is still listed, as a root.
        foreach ($this->categories as $category) {
            if (! isset($this->visited[$category->id])) {
                $this->visit($category, 0);
            }
        }

        return $this->rows;
    }

    /** Appends the category and its branch in order; returns the branch's product total. */
    private function visit(Category $category, int $depth): int
    {
        $this->visited[$category->id] = true;
        $index = count($this->rows);
        $direct = (int) ($this->direct[$category->id] ?? 0);
        $this->rows[] = [
            ...$category->toArray(),
            'is_demo' => str_starts_with($category->slug, 'demo-'),
            'depth' => $depth,
            'products_direct' => $direct,
            'products_total' => $direct,
            'pricing' => $this->pricing($category),
        ];
        $total = $direct;
        foreach ($this->children->get($category->id, collect()) as $child) {
            if (! isset($this->visited[$child->id])) {
                $total += $this->visit($child, $depth + 1);
            }
        }
        $this->rows[$index]['products_total'] = $total;

        return $total;
    }

    /** Nearest category up the chain, itself included, that has a rule: CommercialPricing's walk. */
    private function pricing(Category $category): ?array
    {
        $current = $category;
        $seen = [];
        while ($current && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            if ($rule = $this->rules->get($current->id)) {
                $inherited = $current->id !== $category->id;

                return ['units' => (int) $rule->multiplier_units, 'rule' => $rule->name, 'inherited' => $inherited, 'from' => $inherited ? $current->name : null];
            }
            $current = $this->categories->get($current->parent_id);
        }

        return null;
    }
}
