<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low-Stock Threshold
    |--------------------------------------------------------------------------
    |
    | A product is "low stock" when its stock_quantity is strictly below this
    | value. The low-stock endpoint can override it per request (?threshold=).
    |
    */

    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Order Limits
    |--------------------------------------------------------------------------
    |
    | Upper bounds enforced by StoreOrderRequest so one request cannot lock an
    | unbounded number of product rows or buy an absurd quantity.
    |
    */

    'max_quantity_per_line' => 1000,

    'max_lines_per_order' => 50,

];
