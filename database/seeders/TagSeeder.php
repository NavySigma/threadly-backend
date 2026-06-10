<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'PHP',        'color' => '#777BB4'],
            ['name' => 'Laravel',    'color' => '#FF2D20'],
            ['name' => 'JavaScript', 'color' => '#F7DF1E'],
            ['name' => 'React',      'color' => '#61DAFB'],
            ['name' => 'Vue',        'color' => '#42B883'],
            ['name' => 'Python',     'color' => '#3776AB'],
            ['name' => 'Java',       'color' => '#007396'],
            ['name' => 'Go',         'color' => '#00ADD8'],
            ['name' => 'Docker',     'color' => '#2496ED'],
            ['name' => 'MySQL',      'color' => '#4479A1'],
        ];

        foreach ($tags as $tag) {
            Tag::create([
                'name'       => $tag['name'],
                'slug'       => Str::slug($tag['name']),
                'color'      => $tag['color'],
                'created_at' => now(),
            ]);
        }
    }
}
