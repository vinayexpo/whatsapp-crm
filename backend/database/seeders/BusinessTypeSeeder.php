<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    private const DEFAULT_ORDER_STATUSES = [
        ['slug' => 'pending', 'label' => 'Pending', 'is_terminal' => false],
        ['slug' => 'confirmed', 'label' => 'Confirmed', 'is_terminal' => false],
        ['slug' => 'preparing', 'label' => 'Preparing', 'is_terminal' => false],
        ['slug' => 'out_for_delivery', 'label' => 'Out for Delivery', 'is_terminal' => false],
        ['slug' => 'delivered', 'label' => 'Delivered', 'is_terminal' => true],
        ['slug' => 'cancelled', 'label' => 'Cancelled', 'is_terminal' => true],
    ];

    private const TYPES = [
        [
            'slug' => 'restaurant',
            'name' => 'Restaurant',
            'catalog_labels' => ['category' => 'Menu Category', 'product' => 'Dish', 'variant' => 'Size/Portion'],
        ],
        [
            'slug' => 'electronics',
            'name' => 'Electronics',
            'catalog_labels' => ['category' => 'Category', 'product' => 'Product', 'variant' => 'Model/Spec'],
        ],
        [
            'slug' => 'furniture',
            'name' => 'Furniture',
            'catalog_labels' => ['category' => 'Category', 'product' => 'Item', 'variant' => 'Size/Color'],
        ],
        [
            'slug' => 'grocery',
            'name' => 'Grocery',
            'catalog_labels' => ['category' => 'Aisle', 'product' => 'Item', 'variant' => 'Pack Size'],
        ],
        [
            'slug' => 'clothing',
            'name' => 'Clothing',
            'catalog_labels' => ['category' => 'Category', 'product' => 'Item', 'variant' => 'Size/Color'],
        ],
        [
            'slug' => 'services',
            'name' => 'Services',
            'catalog_labels' => ['category' => 'Category', 'product' => 'Service', 'variant' => 'Package'],
        ],
        [
            'slug' => 'hotels',
            'name' => 'Hotels',
            'catalog_labels' => ['category' => 'Category', 'product' => 'Room Type', 'variant' => 'Occupancy'],
        ],
        [
            'slug' => 'catering',
            'name' => 'Catering',
            'catalog_labels' => ['category' => 'Menu Category', 'product' => 'Item', 'variant' => 'Portion'],
        ],
        [
            'slug' => 'generic',
            'name' => 'Generic',
            'catalog_labels' => ['category' => 'Category', 'product' => 'Product', 'variant' => 'Variant'],
        ],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            BusinessType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'catalog_labels' => $type['catalog_labels'],
                    'default_order_statuses' => self::DEFAULT_ORDER_STATUSES,
                    'is_active' => true,
                ]
            );
        }
    }
}
