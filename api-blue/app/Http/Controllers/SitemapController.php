<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml for the storefront (nginx routes /sitemap.xml here).
 *
 * Only what a visitor can actually open: products and stores of active
 * stores, and categories. Listing deactivated stores is what kept crawlers
 * hitting dead product pages.
 */
class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml';

    private const CACHE_MINUTES = 60;

    public function __invoke(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $base = config('marketplace.storefront_url');
        $urls = [];

        foreach (['', '/products', '/stores', '/categories', '/about', '/career', '/privacy', '/terms'] as $path) {
            $urls[] = [$base.($path === '' ? '/' : $path), null];
        }

        foreach (ProductCategory::query()->whereNotNull('slug')->get(['slug', 'updated_at']) as $category) {
            $urls[] = [$base.'/browse-category/'.rawurlencode($category->slug), $category->updated_at];
        }

        foreach (Store::query()->where('is_active', true)->get(['username', 'updated_at']) as $store) {
            $urls[] = [$base.'/store/'.rawurlencode($store->username), $store->updated_at];
        }

        $products = Product::query()
            ->whereIn('store_id', Store::query()->where('is_active', true)->select('id'))
            ->get(['slug', 'updated_at']);
        foreach ($products as $product) {
            $urls[] = [$base.'/product/'.rawurlencode($product->slug), $product->updated_at];
        }

        $entries = array_map(function (array $url) {
            [$loc, $updatedAt] = $url;
            $lastmod = $updatedAt ? '<lastmod>'.$updatedAt->toAtomString().'</lastmod>' : '';

            return '<url><loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'.$lastmod.'</url>';
        }, $urls);

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', $entries)
            .'</urlset>';
    }
}
