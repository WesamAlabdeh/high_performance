<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@highperformance.test'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
            ]
        );

        Cart::firstOrCreate(['user_id' => $user->id]);
    }
}
