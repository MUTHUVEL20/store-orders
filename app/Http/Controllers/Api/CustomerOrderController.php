<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CustomerNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerOrdersRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerOrderController extends Controller
{
    public function index(CustomerOrdersRequest $request): AnonymousResourceCollection
    {
        $customer = Customer::query()->where('email', $request->email())->first()
            ?? throw new CustomerNotFoundException();

        // Items are eager loaded in one extra query regardless of page size (no N+1).
        $orders = $customer->orders()
            ->with('items')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return OrderResource::collection($orders)->additional([
            'message' => 'Order history retrieved successfully',
            'customer' => (new CustomerResource($customer))->resolve(),
        ]);
    }
}
