<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = Category::where('name', 'إلكترونيات')->first();

        $products = [
            ['name' => 'سماعات لاسلكية', 'name_en' => 'Wireless Headphones', 'price' => 89.99, 'stock' => 50],
            ['name' => 'لوحة مفاتيح ميكانيكية', 'name_en' => 'Mechanical Keyboard', 'price' => 129.00, 'stock' => 30],
            ['name' => 'شاحن سريع', 'name_en' => 'Fast Charger', 'price' => 24.50, 'stock' => 100],
            ['name' => 'ساعة ذكية', 'name_en' => 'Smart Watch', 'price' => 199.00, 'stock' => 20],
            ['name' => 'حافظة هاتف', 'name_en' => 'Phone Case', 'price' => 15.00, 'stock' => 200],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['name' => $product['name']],
                [...$product, 'category_id' => $electronics?->id, 'is_active' => true]
            );
        }
    }
}
