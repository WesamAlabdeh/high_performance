<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'إلكترونيات', 'name_en' => 'Electronics'],
            ['name' => 'ملابس', 'name_en' => 'Clothing'],
            ['name' => 'منزل', 'name_en' => 'Home'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
