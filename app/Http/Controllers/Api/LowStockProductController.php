<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LowStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LowStockProductController extends Controller
{
    public function __invoke(LowStockRequest $request): AnonymousResourceCollection
    {
        $threshold = $request->threshold();

        // Strictly below the threshold; emptiest first, id as a stable tie-breaker for pagination.
        $products = Product::query()
            ->where('stock_quantity', '<', $threshold)
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ProductResource::collection($products)->additional([
            'message' => 'Low-stock products retrieved successfully',
            'threshold' => $threshold,
        ]);
    }
}
