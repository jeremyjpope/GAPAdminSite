<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Access areas. A user is granted zero or more areas; super admins have all.
 * The key is stored in user_areas.area and used by the "area" route filter.
 */
class Areas extends BaseConfig
{
    public array $list = [
        'students'    => ['name' => 'Students',      'desc' => 'View and edit the student directory, contacts, vehicles and photos.'],
        'tuition'     => ['name' => 'Tuition',       'desc' => 'Payment plans, record payments and see balances.'],
        'housing'     => ['name' => 'Host Homes',    'desc' => 'Host home families and assignments.'],
        'mentors'     => ['name' => 'Mentors',       'desc' => 'Mentor assignments (mentors are volunteers with a mentor role).'],
        'volunteers'  => ['name' => 'Volunteers',    'desc' => 'All adults who serve: contact details, roles, compliance.'],
        'groups'      => ['name' => 'Groups',        'desc' => 'Team, Friday night, life skills and accountability groupings.'],
        'schedule'    => ['name' => 'Schedule',      'desc' => 'Course catalog, instructors and the weekly / yearly class map.'],
        'transcripts' => ['name' => 'Transcripts',   'desc' => 'Course catalog, grades and transcript PDFs.'],
        'applicants'  => ['name' => 'Applicants',    'desc' => 'Applicants, admissions checklist, interview rubric scores, accept into the program.'],
        'admin'       => ['name' => 'Site Admin',    'desc' => 'Manage who can log in, program years and settings.'],
    ];
}
