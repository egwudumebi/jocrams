<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Event;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@jocrams.test')->first();

        if (! $admin) {
            return;
        }

        $newsCategory = Category::query()->firstOrCreate(
            ['slug' => 'announcements', 'type' => 'news'],
            ['name' => 'Announcements', 'is_active' => true],
        );

        $eventsCategory = Category::query()->firstOrCreate(
            ['slug' => 'conferences', 'type' => 'events'],
            ['name' => 'Conferences', 'is_active' => true],
        );

        NewsArticle::query()->firstOrCreate(
            ['slug' => 'welcome-to-jocrams'],
            [
                'author_id' => $admin->id,
                'category_id' => $newsCategory->id,
                'title' => 'Welcome to Jocrams Association Platform',
                'excerpt' => 'Our new digital platform is now live for members and the public.',
                'body' => '<p>We are excited to launch our enterprise association management platform. Members can now apply online, manage dues, and access digital credentials.</p>',
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => now()->subDays(3),
            ],
        );

        NewsArticle::query()->firstOrCreate(
            ['slug' => 'annual-general-meeting-2026'],
            [
                'author_id' => $admin->id,
                'category_id' => $newsCategory->id,
                'title' => 'Annual General Meeting 2026',
                'excerpt' => 'Save the date for our upcoming AGM.',
                'body' => '<p>Join us for the Annual General Meeting where we will review achievements and elect new officers.</p>',
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => now()->subDay(),
            ],
        );

        Event::query()->firstOrCreate(
            ['slug' => 'annual-conference-2026'],
            [
                'organizer_id' => $admin->id,
                'category_id' => $eventsCategory->id,
                'title' => 'Annual Conference 2026',
                'description' => 'Our flagship annual conference for all members.',
                'body' => '<p>Three days of workshops, networking, and keynote sessions.</p>',
                'location' => 'Lagos Convention Centre',
                'starts_at' => now()->addMonths(2),
                'ends_at' => now()->addMonths(2)->addDays(2),
                'registration_opens_at' => now(),
                'registration_closes_at' => now()->addMonths(2)->subWeek(),
                'max_attendees' => 500,
                'fee' => 15000,
                'currency' => 'NGN',
                'visibility' => 'public',
                'status' => 'published',
            ],
        );

        Branch::query()->firstOrCreate(
            ['slug' => 'lagos-headquarters'],
            [
                'name' => 'Lagos Headquarters',
                'address' => '12 Marina Road',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'phone' => '+2348012345678',
                'email' => 'lagos@jocrams.test',
                'contact_person' => 'Branch Coordinator',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Branch::query()->firstOrCreate(
            ['slug' => 'abuja-branch'],
            [
                'name' => 'Abuja Branch',
                'address' => '45 Central Area',
                'city' => 'Abuja',
                'state' => 'FCT',
                'country' => 'Nigeria',
                'phone' => '+2348098765432',
                'email' => 'abuja@jocrams.test',
                'contact_person' => 'Regional Manager',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
    }
}
