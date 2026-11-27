<?php

namespace App\Services\Commerce;

use App\Models\Inventory;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class ReleaseOrderInventory
{
    public function run(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'cancelled') {
                return;
            }
            foreach ($locked->items()->lockForUpdate()->get() as $item) {
                if (! data_get($item->selected_options, 'inventory_reserved')) {
                    continue;
                }
                Inventory::where('company_id', $order->company_id)->where('branch_id', $order->branch_id)->where('product_id', $item->product_id)->where('product_variant_id', $item->product_variant_id)->increment('stock_quantity', $item->quantity);
                $item->update(['selected_options' => array_merge($item->selected_options ?? [], ['inventory_reserved' => false])]);
            }
        });
    }
}
