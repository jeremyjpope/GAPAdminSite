<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Volunteers extends BaseConfig
{
    /** Role tags a volunteer can hold (several at once). Order is display order. */
    public array $roles = [
        'Host Family', 'Sub Host Family', 'Host Home Sponsor', 'Guys Mentor', 'Girls Mentor',
        'Life Skills', 'Fitness', 'Career Moment', 'Special Topic', 'Instructor',
        'Leadership Team/Advisory', 'Meal Provider', 'Potential Volunteer', 'Guest',
    ];

    /** Roles that make someone show up as a mentor option. */
    public array $mentorRoles = ['Guys Mentor', 'Girls Mentor'];

    /** Life-skills categories offered as suggestions (free text is allowed). */
    public array $lifeSkillsCategories = [
        'Home Guys', 'Home Girls', 'Outdoors Guys', 'Outdoors Girls', 'Artisanship Guys', 'Artisanship Girls',
        'Automotive', 'Fitness', 'Vocation', 'Creation Care',
    ];

    public array $statuses = ['active' => 'Active', 'inactive' => 'Inactive', 'potential' => 'Potential'];
}
