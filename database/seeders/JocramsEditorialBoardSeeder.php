<?php

namespace Database\Seeders;

use App\Models\EditorialBoardMember;
use Illuminate\Database\Seeder;

class JocramsEditorialBoardSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            [
                'name' => 'Lawrence Ekwok, PhD',
                'role_title' => 'Chairman, Board of Trustees',
                'affiliation' => 'Department of Mass Communication, Faculty of Communication and Media Studies, University of Calabar, Calabar, Nigeria',
                'sort_order' => 10,
            ],
            [
                'name' => 'Prof. Patrick Ene Okon',
                'role_title' => 'Editor-in-Chief',
                'affiliation' => 'Department of Mass Communication, Faculty of Communication and Media Studies, University of Calabar, Calabar, Nigeria',
                'sort_order' => 20,
            ],
            [
                'name' => 'Prof. Presly Obukoadata',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'Southern Delta University, Delta State, Nigeria',
                'sort_order' => 30,
            ],
            [
                'name' => 'Prof. Emeritus Andy O. Alali',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'California State University, Bakersfield, USA',
                'sort_order' => 31,
            ],
            [
                'name' => 'Prof. K. O. Nworgu',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'Imo State University, Nigeria',
                'sort_order' => 32,
            ],
            [
                'name' => 'Prof. Mustapha N. Malam',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'Bayero University, Kano, Nigeria',
                'sort_order' => 33,
            ],
            [
                'name' => 'Prof. Olubunmi Ajibade',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'McPherson University, Ogun State, Nigeria',
                'sort_order' => 34,
            ],
            [
                'name' => 'Prof. Caleb Terngu Chile',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'Rev. Fr. Moses Orshio Adasu University, Makurdi, Nigeria',
                'sort_order' => 35,
            ],
            [
                'name' => 'Prof. Ashong C. Ashong',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'University of Uyo, Akwa Ibom State, Nigeria',
                'sort_order' => 36,
            ],
            [
                'name' => 'Prof. Esekong Andrew-Essien',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'University of Calabar, Calabar, Nigeria',
                'sort_order' => 37,
            ],
            [
                'name' => 'Prof. Josiah Sabo Kente',
                'role_title' => 'Editorial Adviser',
                'affiliation' => 'Taraba State University, Taraba, Nigeria',
                'sort_order' => 38,
            ],
            [
                'name' => 'Prof. Kenneth Tsebe Asor',
                'role_title' => 'Associate Editor',
                'affiliation' => 'Veritas University, Abuja, Nigeria',
                'sort_order' => 40,
            ],
            [
                'name' => 'Prof. Innocent Pascal Ihechu',
                'role_title' => 'Associate Editor',
                'affiliation' => 'Abia State University, Abia State, Nigeria',
                'sort_order' => 41,
            ],
            [
                'name' => 'Dr. Toyin Segun Onayinka',
                'role_title' => 'Associate Editor',
                'affiliation' => 'Federal University, Oye-Ekiti, Ekiti State, Nigeria',
                'sort_order' => 42,
            ],
            [
                'name' => 'Ogunbadejo Samuel Idowu, PhD',
                'role_title' => 'Managing Editor',
                'affiliation' => 'Nnamdi Azikiwe University, Awka, Nigeria',
                'sort_order' => 50,
            ],
            [
                'name' => 'Dr. Gloria Omale Eneh',
                'role_title' => 'Managing Editor',
                'affiliation' => 'Federal University of Technology, Minna, Nigeria',
                'sort_order' => 51,
            ],
            [
                'name' => 'Dr. Obioma Ozioko',
                'role_title' => 'Review Editor',
                'affiliation' => 'Gregory Okoye University, Enugu, Nigeria',
                'sort_order' => 60,
            ],
            [
                'name' => 'Dr. Favour Nwantah',
                'role_title' => 'Review Editor',
                'affiliation' => 'Covenant University, Ota, Ogun State, Nigeria',
                'sort_order' => 61,
            ],
            [
                'name' => 'Dr. Cynthia Ifeoma Odenigbo',
                'role_title' => 'Review Editor',
                'affiliation' => 'Maduka University, Nsukka, Nigeria',
                'sort_order' => 62,
            ],
            [
                'name' => 'Dr. Edang Yolanda Ekpo Bassey',
                'role_title' => 'Review Editor',
                'affiliation' => 'Cross River State University, Calabar, Nigeria',
                'sort_order' => 63,
            ],
            [
                'name' => 'Dr. Qaribu Yahaya Nasidi',
                'role_title' => 'Review Editor',
                'affiliation' => 'Ahmadu Bello University, Zaria, Nigeria',
                'sort_order' => 64,
            ],
            [
                'name' => 'Dr. Anthony Ekpo Bassey',
                'role_title' => 'Production Editor',
                'affiliation' => 'Department of Mass Communication, Faculty of Communication and Media Studies, University of Calabar, Calabar, Nigeria',
                'sort_order' => 70,
            ],
        ];

        foreach ($members as $member) {
            EditorialBoardMember::query()->updateOrCreate(
                [
                    'name' => $member['name'],
                    'role_title' => $member['role_title'],
                ],
                [
                    'affiliation' => $member['affiliation'],
                    'bio' => null,
                    'sort_order' => $member['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        // Normalize older seed spelling if present.
        EditorialBoardMember::query()
            ->where('name', 'Dr Anthony Ekpo Bassey')
            ->where('role_title', 'Production Editor')
            ->update([
                'name' => 'Dr. Anthony Ekpo Bassey',
                'affiliation' => 'Department of Mass Communication, Faculty of Communication and Media Studies, University of Calabar, Calabar, Nigeria',
                'sort_order' => 70,
                'is_active' => true,
            ]);
    }
}
