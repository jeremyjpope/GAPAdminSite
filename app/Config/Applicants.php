<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Admissions pipeline lists. Edit here, not in code. */
class Applicants extends BaseConfig
{
    /** status key => [label, bootstrap colour] */
    public array $statuses = [
        'applied'    => ['Applied',             'secondary'],
        'references' => ['References pending',  'info'],
        'interview'  => ['Interview scheduled', 'primary'],
        'interviewed'=> ['Interviewed',         'primary'],
        'accepted'   => ['Accepted',            'success'],
        'waitlisted' => ['Waitlisted',          'warning'],
        'declined'   => ['Declined',            'danger'],
        'withdrawn'  => ['Withdrawn',           'dark'],
    ];

    /** Checklist steps in display order: key => label. */
    public array $steps = [
        'applied'       => 'Application received',
        'preview'       => 'Preview day',
        'video'         => 'Video received',
        'personal_ref'  => 'Personal reference',
        'spiritual_ref' => 'Spiritual reference',
        'interview'     => 'Interview',
        'decision'      => 'Decision sent',
        'commitment'    => 'Commitment letter',
        'background'    => 'Background check / SAAT / placed in MP',
        'books'         => 'Books ordered',
        'canvas'        => 'Added to Moodle / Canvas course',
        'newsletter'    => 'Newsletter',
        'dates_doc'     => 'Important dates document sent',
    ];

    /** Interview rubric categories: key => [label, description]. Scores are 1–10. */
    public array $rubric = [
        'hunger'   => ['Hunger',   'Takes personal responsibility, seeks spiritual growth, and has individual drive.'],
        'humility' => ['Humility', 'Teachable spirit, respects authority, and has self-control.'],
        'honor'    => ['Honor',    'Respectful to authority and standards, presents in a dignified manner, and has integrity.'],
        'fit'      => ['Fit',      'Alignment with the GAP Ethic and gets along with peers.'],
    ];
}
