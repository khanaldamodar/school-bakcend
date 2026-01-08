<?php

namespace Database\Seeders;

use App\Models\Admin\WebsiteSetting;
use Illuminate\Database\Seeder;

class WebsiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WebsiteSetting::create([
            'hero_title' => 'Shaping Bright Futures with Quality Education',
            'hero_desc' => 'Welcome to our institution, where we foster academic excellence and character development in a supportive and innovative learning environment.',
            'hero_image' => 'https://images.unsplash.com/photo-1523050853064-8521a30c02e9?q=80&w=2070&auto=format&fit=crop',
            'heroButtonText' => 'Learn More',
            'heroButtonUrl' => '/about',
            'number_of_teachers' => 45,
            'number_of_students' => 850,
            'year_of_experience' => 15,
            'number_of_events' => 120,
            'total_awards' => 35,
            'total_courses' => 12,
            'mission' => 'To empower students with knowledge, skills, and values that enable them to succeed in a globalized world and contribute positively to society.',
            'vision' => 'To be a leading educational institution known for academic excellence, innovation, and producing well-rounded citizens.',
            'pass_rate' => 98.5,
            'top_score' => 4.0,
            'history' => 'Founded in 2008, our school has grown from a small community center to a leading educational institution in the region, consistently producing top-performing students.',
            'principal_name' => 'Dr. Ramesh Sharma',
            'principal_image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=1974&auto=format&fit=crop',
            'principal_message' => "Education is the most powerful weapon which you can use to change the world. At our school, we are committed to providing every student with a world-class education that prepares them for the challenges of tomorrow.",
        ]);
    }
}
