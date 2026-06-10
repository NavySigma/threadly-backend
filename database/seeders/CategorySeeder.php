<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Web Development',     'description' => 'Diskusi seputar pengembangan web, frontend, dan backend.'],
            ['name' => 'Mobile Development',  'description' => 'Android, iOS, Flutter, React Native, dan sejenisnya.'],
            ['name' => 'DevOps & Cloud',      'description' => 'CI/CD, Docker, Kubernetes, AWS, GCP, dan Azure.'],
            ['name' => 'Database',            'description' => 'SQL, NoSQL, query optimization, dan desain schema.'],
            ['name' => 'Programming Basics',  'description' => 'Algoritma, struktur data, dan konsep dasar pemrograman.'],
            ['name' => 'Security',            'description' => 'Keamanan aplikasi, vulnerability, dan best practices.'],
            ['name' => 'UI/UX',               'description' => 'Desain antarmuka, user experience, dan aksesibilitas.'],
            ['name' => 'Data Science',        'description' => 'Machine learning, data analysis, dan AI.'],
            ['name' => 'Game Development',    'description' => 'Game engine, game design, dan pengembangan game.'],
            ['name' => 'Career & General',    'description' => 'Diskusi karir, tips kerja, dan topik umum programming.'],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name'        => $category['name'],
                'slug'        => Str::slug($category['name']),
                'description' => $category['description'],
                'created_at'  => now(),
            ]);
        }
    }
}
