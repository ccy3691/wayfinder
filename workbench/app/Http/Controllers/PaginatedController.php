<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class PaginatedController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            return $next($request);
        });
    }

    public function show()
    {
        //
    }

    public function pagination()
    {
        return Inertia::render('PaginationTest', [
            'products' => Product::paginate(10),
        ]);
    }

    public function simplePagination()
    {
        return Inertia::render('SimplePaginationTest', [
            'productsSimple' => Product::simplePaginate(10),
        ]);
    }

    public function relationPagination()
    {
        $user = new User();

        return Inertia::render('RelationPaginationTest', [
            'owned' => $user->ownedProducts()->paginate(10),
            'favoritesSimple' => $user->favoriteCategories()->simplePaginate(10),
        ]);
    }

    public function relationalPagination()
    {
        $product = Product::first();
        $products = $product->relatedProducts()->paginate(10);
    
        return Inertia::render('RelationalPaginationTest', [
            'products' => $products,
        ]);
    }

    public function chainedRelationPagination()
    {
        $user = new User();

        return Inertia::render('ChainedRelationPaginationTest', [
            'chained' => $user->favoriteCategories()->categoryProducts()->paginate(10),
        ]);
    }
}
