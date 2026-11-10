<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Creates the first super admin and the current program year with a transcript catalog.
 * Safe to re-run: it only inserts what is missing.
 */
class InitialSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        if (! $this->db->table('users')->where('email', 'jpope@dentonbible.org')->countAllResults()) {
            $this->db->table('users')->insert([
                'username' => 'jpope', 'email' => 'jpope@dentonbible.org', 'fullname' => 'Jeremy Pope',
                'is_superadmin' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! $this->db->table('program_years')->where('label', '2026-27')->countAllResults()) {
            $this->db->table('program_years')->insert([
                'label' => '2026-27', 'year_number' => 8,
                'start_date' => '2026-08-01', 'end_date' => '2027-05-31',
                'moodle_overall_course_id' => 10,
                'tuition_year_early' => 4000, 'tuition_year_late' => 4500, 'tuition_cutoff_date' => '2026-01-01',
                'program_description' => 'Nine-month, full-time discipleship program',
                'transcript_notes' => 'Also completed: five Milestone Events (retreats and experiential learning) and the Christlike Formation component (bi-monthly mentoring, monthly discipleship, peer accountability, and weekly ministry service).',
                'grade_scale' => json_encode([
                    ['letter' => 'A', 'min' => 90, 'points' => 4.0],
                    ['letter' => 'B', 'min' => 80, 'points' => 3.0],
                    ['letter' => 'C', 'min' => 70, 'points' => 2.0],
                    ['letter' => 'D', 'min' => 60, 'points' => 1.0],
                    ['letter' => 'F', 'min' => 0,  'points' => 0.0],
                ]),
                'is_current' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $yearId = $this->db->insertID();

            // Starting catalog, modelled on the 2022-23 transcript. Edit hours/terms per year in Transcripts > Catalog.
            $catalog = [
                ['Pillar I: Bible', 'Biblical Exposition', 'Expository teaching through books of the Bible', 'Full Year', 38],
                ['Pillar I: Bible', 'Bible Study Methods', 'Hermeneutics and inductive Bible study', 'Fall 2026', 17],
                ['Pillar I: Bible', 'Chapel', 'Weekly worship and teaching', 'Full Year', 19],
                ['Pillar I: Bible', 'Devotional', 'Weekly devotional study', 'Full Year', 19],
                ['Pillar II: Worldview', 'Christian Worldview', 'Biblical and secular worldviews compared', 'Full Year', 31],
                ['Pillar II: Worldview', 'Cultural Engagement', 'Applying a biblical response to culture', 'Fall 2026', 17],
                ['Pillar II: Worldview', 'Church History', 'Survey of the history of the church', 'Spring 2027', 21],
                ['Pillar III: Leadership', 'Biblical Leadership', 'Servant leadership across nine life spheres', 'Full Year', 75],
                ['Pillar III: Leadership', 'Vocational Mission', 'Vocational assessment, workplace experiences and skills', 'Full Year', 50],
                ['Pillar III: Leadership', 'Biblical Manhood', 'A biblical vision of manhood', 'Full Year', 31],
                ['Pillar III: Leadership', 'Biblical Womanhood', 'A biblical vision of womanhood', 'Full Year', 31],
                ['Pillar III: Leadership', 'Purity & Addiction', 'Biblical purity and freedom from addiction', 'Full Year', 38],
                ['Pillar IV: Life Skills', 'Artisanship', 'Woodworking, knifemaking, and metalworking', 'Fall 2026', 38],
                ['Pillar IV: Life Skills', 'Automotive', 'Engine systems, tires, fluids, and maintenance', 'Fall 2026', 19],
                ['Pillar IV: Life Skills', 'Creation Care', 'Gardening, sustainability, and agriculture', 'Spring 2027', 19],
                ['Pillar IV: Life Skills', 'Home', 'Electrical, plumbing, maintenance, cooking, hospitality', 'Full Year', 57],
                ['Pillar IV: Life Skills', 'Outdoors', 'First Aid/CPR, firearms safety, camping, survival', 'Full Year', 114],
                ['Pillar IV: Life Skills', 'Physical Fitness', 'Health and wellness, run training, self-defense', 'Full Year', 198],
                ['Special Topics', 'Personal Budgeting', 'Budgeting methods and a personal budget plan', 'Full Year', 8],
                ['Special Topics', 'Communication', 'Oral presentation and public speaking', 'Full Year', 8],
                ['Special Topics', 'Biblical Dating', 'Biblical convictions for relationships', 'Full Year', 8],
            ];
            $rows = [];
            foreach ($catalog as $i => [$pillar, $name, $desc, $term, $hours]) {
                $rows[] = ['program_year_id' => $yearId, 'pillar' => $pillar, 'name' => $name, 'description' => $desc,
                           'term' => $term, 'hours' => $hours, 'sort_order' => ($i + 1) * 10,
                           'created_at' => $now, 'updated_at' => $now];
            }
            $this->db->table('transcript_courses')->insertBatch($rows);
        }
    }
}
