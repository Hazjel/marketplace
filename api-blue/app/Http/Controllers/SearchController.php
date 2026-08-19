<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Http\Request;

/**
 * Typeahead suggestions as the user types — deliberately separate from
 * ProductController::index (the full paginated search results page).
 * Kept lightweight on purpose: capped result counts, no filters, no
 * pagination — this exists purely to fill a dropdown while the user is
 * still typing, before they've committed to a full search.
 */
class SearchController extends Controller
{
    public function suggestions(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Same 2-char floor as web's debounce trigger — anything shorter is
        // too noisy to be a useful suggestion and wastes a query on every
        // keystroke.
        if (mb_strlen($q) < 2) {
            return ResponseHelper::jsonResponse(true, 'OK', [
                'products' => [],
                'categories' => [],
                'stores' => [],
            ], 200);
        }

        $products = Product::search($q)
            // Exact-prefix matches ("iph" -> "iPhone 15") read as more useful
            // suggestions than a mid-string match, so surface those first.
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$q.'%'])
            ->orderBy('created_at', 'desc')
            ->with(['productImages', 'store'])
            ->limit(6)
            ->get();

        $categories = ProductCategory::where('name', 'like', '%'.$q.'%')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$q.'%'])
            ->limit(4)
            ->get(['id', 'name', 'slug']);

        $stores = Store::where('name', 'like', '%'.$q.'%')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$q.'%'])
            ->limit(4)
            ->get(['id', 'name', 'username', 'logo']);

        return ResponseHelper::jsonResponse(true, 'OK', [
            'products' => ProductResource::collection($products),
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
            ]),
            'stores' => $stores->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'username' => $s->username,
                'logo' => $s->logo && ! str_starts_with($s->logo, 'http') ? asset('storage/'.$s->logo) : $s->logo,
            ]),
        ], 200);
    }
}
