<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ThreadlySeeder extends Seeder
{
    public function run(): void
    {
        $usersData = [
            ['username' => 'navyz',     'bio' => 'Frontend developer, suka React & Tailwind'],
            ['username' => 'eris',      'bio' => 'Backend engineer, pecinta Laravel'],
            ['username' => 'evi',       'bio' => 'Fullstack developer, belajar sambil ngajar'],
            ['username' => 'asya',      'bio' => 'Mobile dev, Flutter enthusiast'],
            ['username' => 'ridho',     'bio' => 'DevOps engineer, container is life'],
            ['username' => 'natasya',   'bio' => 'UI/UX designer yang bisa coding'],
            ['username' => 'naufal',    'bio' => 'Machine learning & data science'],
            ['username' => 'gopek',     'bio' => 'Security researcher, bug hunter'],
            ['username' => 'PakFajri',  'bio' => 'Dosen dan mentor programming'],
            ['username' => 'tito',      'bio' => 'Game developer, Unity & Unreal'],
            ['username' => 'anggara',   'bio' => 'Sysadmin & network engineer'],
        ];

        $password = Hash::make('Aksata12');
        $userIds = [];
        $order = 1;

        foreach ($usersData as $data) {
            $pass = $data['username'] === 'eris'
                ? Hash::make('ErisRahma123')
                : $password;

            $user = User::firstOrCreate(
                ['email' => $data['username'] . '@threadly.com'],
                [
                    'username'          => $data['username'],
                    'password_hash'     => $pass,
                    'bio'               => $data['bio'],
                    'reputation_points' => rand(10, 500),
                ]
            );
            $userIds[$data['username']] = $user->id;
        }

        $categories = Category::pluck('id', 'name')->toArray();
        $tags = Tag::pluck('id', 'name')->toArray();

        $posts = [
            [
                'user' => 'navyz', 'category' => 'Web Development',
                'title' => 'Best practices React component structure?',
                'body'  => "Halo teman-teman, gua lagi mengerjakan project React yang cukup besar dan bingung bagaimana cara structuring components yang baik. Ada yang punya saran? Apakah better pake atomic design atau feature-based?",
                'tags'  => ['React', 'JavaScript'],
            ],
            [
                'user' => 'navyz', 'category' => 'UI/UX',
                'title' => 'Tips animasi TailwindCSS biar smooth',
                'body'  => "Gua lagi explore animasi di TailwindCSS, tapi kadang masih kurang smooth. Ada yang punya tips atau library tambahan yang recommended? Framer Motion vs GSAP buat React, better mana?",
                'tags'  => ['React', 'JavaScript'],
            ],
            [
                'user' => 'eris', 'category' => 'Web Development',
                'title' => 'Laravel 11: Queue vs Job bingung bedanya',
                'body'  => "Dokumentasi Laravel bilang Queue itu untuk defer tasks, sementara Job adalah tasknya sendiri. Tapi masih agak rancu. Kapan kita pake Queue class langsung vs bikin Job? Mohon pencerahan.",
                'tags'  => ['Laravel', 'PHP'],
            ],
            [
                'user' => 'eris', 'category' => 'Database',
                'title' => 'Optimasi query Eloquent dengan chunk',
                'body'  => "Ketika fetching data besar, Eloquent sering lemot. Ada yang udah pernah pake chunk() atau cursor()? Sharing pengalaman dong biar kita bisa belajar dari real case.",
                'tags'  => ['Laravel', 'PHP', 'MySQL'],
            ],
            [
                'user' => 'evi', 'category' => 'Programming Basics',
                'title' => 'Single Responsibility Principle的例子',
                'body'  => "Gua masih struggle sama SRP. Kadang bingung batasan 'satu tanggung jawab' itu sampai mana. Contoh konkritnya apa ya? Mungkin ada yang bisa kasih kode snippet perbandingan sebelum dan sesudah.",
                'tags'  => ['PHP'],
            ],
            [
                'user' => 'evi', 'category' => 'Web Development',
                'title' => 'Vue vs React di 2025, masih worth it belajar Vue?',
                'body'  => "Gua udah熟悉 React, tapi pengen coba Vue. Apa masih relevan belajar Vue di 2025? Atau mending deepen aja di React ecosystem? Input dari teman-teman sangat diharapkan.",
                'tags'  => ['Vue', 'React', 'JavaScript'],
            ],
            [
                'user' => 'asya', 'category' => 'Mobile Development',
                'title' => 'Flutter Riverpod vs Bloc, mana yang更适合?',
                'body'  => "Gua baru mulai Flutter dan bingung milih state management. Riverpod katanya modern dan simple, Bloc lebih structured. Untuk production app skala medium, better mana ya?",
                'tags'  => ['JavaScript'],
            ],
            [
                'user' => 'asya', 'category' => 'Mobile Development',
                'title' => 'React Native vs Flutter buat startup app',
                'body'  => "Tim gua mau bikin MVP buat startup, bingung milih React Native atau Flutter. Tim ada yang熟悉 JS ada yang familiar Dart. Dari segi performance dan development speed, mana yang lebih recommended?",
                'tags'  => ['JavaScript', 'React'],
            ],
            [
                'user' => 'ridho', 'category' => 'DevOps & Cloud',
                'title' => 'Docker compose untuk Laravel production',
                'body'  => "Gua mau deploy Laravel pake Docker compose. Ada yang udah punya template atau best practice? Mulai dari php-fpm, nginx, queue worker samua cron job. Sharing dong!",
                'tags'  => ['Docker', 'Laravel', 'PHP'],
            ],
            [
                'user' => 'ridho', 'category' => 'DevOps & Cloud',
                'title' => 'CI/CD pipeline dengan GitHub Actions',
                'body'  => "Gua bikin pipeline buat Laravel project: run tests, deploy ke server. Tapi kadang masih ada failed deployment. Minta review dong kalo ada yang mau sharing workflow GitHub Actions mereka.",
                'tags'  => ['Laravel', 'Docker'],
            ],
            [
                'user' => 'natasya', 'category' => 'UI/UX',
                'title' => 'Dark mode di web app, penting ga sih?',
                'body'  => "Gua diskusi sama tim, mereka bilang dark mode cuma gimmick. Tapi data bilang banyak user suka dark mode. Kalo menurut teman-teman, worth it ga implement dark mode? At least effortnya?",
                'tags'  => ['JavaScript'],
            ],
            [
                'user' => 'natasya', 'category' => 'UI/UX',
                'title' => 'Figma to code, workflow yang efisien',
                'body'  => "Gua sering frustasi pas mindahin design Figma ke code. Apakah better pake plugin kaya Anima atau manual aja? Ada workflow recommendation biar pixel perfect?",
                'tags'  => ['React'],
            ],
            [
                'user' => 'naufal', 'category' => 'Data Science',
                'title' => 'Python vs R buat data analysis, which one?',
                'body'  => "Gua baru mau belajar data analysis. Antara Python pake pandas atau R dengan tidyverse. Background gua web developer (PHP/JS). Mending belajar yang mana ya?",
                'tags'  => ['Python'],
            ],
            [
                'user' => 'naufal', 'category' => 'Database',
                'title' => 'Implementasi vector database buat RAG app',
                'body'  => "Gua lagi explore Retrieval-Augmented Generation. Bingung milih vector database: Pinecone, Weaviate, atau Qdrant? Atau mending pake PostgreSQL with pgvector aja?",
                'tags'  => ['Python', 'MySQL'],
            ],
            [
                'user' => 'gopek', 'category' => 'Security',
                'title' => 'SQL Injection masih relevan di 2025?',
                'body'  => "Banyak yang bilang SQL Injection udah old news karena ORM. Tapi realitanya masih banyak漏洞 di legacy code. Ada yang pernah nemu case SQL Injection di production? Share pengalaman dong.",
                'tags'  => ['PHP', 'MySQL'],
            ],
            [
                'user' => 'gopek', 'category' => 'Security',
                'title' => 'XSS prevention di React, apa aja yang perlu diwaspadai?',
                'body'  => "React katanya auto-escape XSS. Tapi masih ada potential vulnerability kayak dangerouslySetInnerHTML, href injection, dll. Ada best practice atau checklist yang bisa di-share?",
                'tags'  => ['React', 'JavaScript'],
            ],
            [
                'user' => 'PakFajri', 'category' => 'Programming Basics',
                'title' => 'Tips belajar coding buat pemula di 2025',
                'body'  => "Sebagai dosen, gua sering ditanya mahasiswa: 'Pak, mulai dari mana kalo mau belajar coding?' Gua pengen kumpulin insight dari praktisi biar bisa gua share ke mereka. Menurut teman-teman, beginner harus mulai dari bahasa apa dan fokus ke apa?",
                'tags'  => ['PHP', 'Python'],
            ],
            [
                'user' => 'PakFajri', 'category' => 'Web Development',
                'title' => 'Arsitektur MVC vs Clean Architecture di Laravel',
                'body'  => "Di kampus gua ngajarin MVC, tapi di industri banyak yang pake Clean Architecture atau DDD. Seberapa penting sih pindah ke Clean Architecture untuk project Laravel? Atau MVC still enough?",
                'tags'  => ['Laravel', 'PHP'],
            ],
            [
                'user' => 'tito', 'category' => 'Game Development',
                'title' => 'Unity vs Unreal buat indie developer',
                'body'  => "Gua mau bikin game indie 2D/3D simple. Budget terbatas. Unity katanya lebih ringan dan banyak tutorial, Unreal grafisnya gila. Mending mana ya untuk solo developer?",
                'tags'  => ['JavaScript'],
            ],
            [
                'user' => 'tito', 'category' => 'Game Development',
                'title' => 'Optimasi performa game di mobile device',
                'body'  => "Game gua lumayan lag di device mid-range. Udah pake LOD, occlusion culling, dan texture compression. Apa lagi yang perlu dioptimasi? Share tips dong!",
                'tags'  => ['JavaScript'],
            ],
            [
                'user' => 'anggara', 'category' => 'DevOps & Cloud',
                'title' => 'Nginx reverse proxy untuk multiple apps',
                'body'  => "Gua setup server pake Nginx sebagai reverse proxy buat beberapa aplikasi web. Ada yang punya sample config yang rapi? Termasuk SSL termination, load balancing, dan caching.",
                'tags'  => ['Docker'],
            ],
            [
                'user' => 'anggara', 'category' => 'Security',
                'title' => 'Server hardening setelah kena hack',
                'body'  => "Server gua baru kena brute force attack. Udah secure kan ulang, tapi mau minta checklist hardening dari teman-teman. Mulai dari fail2ban, selinux, firewall, samua auto update.",
                'tags'  => ['Docker', 'MySQL'],
            ],
        ];

        foreach ($posts as $postData) {
            $categoryName = $postData['category'];
            $categoryId = $categories[$categoryName] ?? null;
            if (!$categoryId) continue;

            $userId = $userIds[$postData['user']] ?? null;
            if (!$userId) continue;

            $post = Post::create([
                'user_id'     => $userId,
                'category_id' => $categoryId,
                'title'       => $postData['title'],
                'body'        => $postData['body'],
                'status'      => 'open',
                'view_count'  => rand(20, 500),
                'vote_score'  => rand(-5, 20),
                'created_at'  => now()->subDays(rand(0, 60)),
                'updated_at'  => now()->subDays(rand(0, 10)),
            ]);

            foreach ($postData['tags'] as $tagName) {
                $tagId = $tags[$tagName] ?? null;
                if ($tagId) {
                    $post->tags()->attach($tagId, ['id' => Str::uuid()]);
                }
            }
        }
    }
}
