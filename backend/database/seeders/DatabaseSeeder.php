<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use App\Services\HuggingFaceService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrateur', 'password' => 'password', 'role' => 'admin']
        );

        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'Utilisateur Démo', 'password' => 'password', 'role' => 'user']
        );

        $hf = app(HuggingFaceService::class);

        $samples = [
            ['user' => $user,  'content' => 'Produit incroyable ! Livraison rapide et excellente qualité. Je recommande vivement.'],
            ['user' => $user,  'content' => 'Le prix est trop élevé pour ce que l\'on reçoit. Le service client était désagréable.'],
            ['user' => $admin, 'content' => 'Site correct, le paiement était un peu lent mais l\'article est arrivé à temps.'],
            ['user' => $user,  'content' => 'Cassé à l\'arrivée. Expérience horrible, je demande un remboursement.'],
            ['user' => $admin, 'content' => 'Excellent rapport qualité-prix, j\'adore le design. Livraison super rapide.'],
        ];

        foreach ($samples as $s) {
            $analysis = $hf->analyze($s['content']);
            Review::create([
                'user_id'   => $s['user']->id,
                'content'   => $s['content'],
                'sentiment' => $analysis['sentiment'],
                'score'     => $analysis['score'],
                'topics'    => $analysis['topics'],
            ]);
        }
    }
}
