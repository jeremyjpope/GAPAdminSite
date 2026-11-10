<?php

namespace App\Models;

use CodeIgniter\Model;

class NoteModel extends Model
{
    protected $table         = 'notes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['subject_type', 'subject_id', 'noted_on', 'noted_time', 'category', 'body', 'user_id', 'author_name', 'edited_at'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    public const CATEGORIES = [
        'general'    => 'General',
        'strike'     => 'Strike',
        'discipline' => 'Discipline / infraction',
        'contact'    => 'Contact with parent / host',
        'housing'    => 'Housing',
        'medical'    => 'Medical',
        'praise'     => 'Praise',
        'reference'  => 'Reference',
        'interview'  => 'Interview',
        'decision'   => 'Decision / acceptance',
        'compliance' => 'Background check / SAAT / MP',
    ];

    public const SUBJECTS = ['student' => 'students', 'host_home' => 'housing', 'volunteer' => 'volunteers', 'course' => 'schedule', 'applicant' => 'applicants']; // subject => required area

    /** Notes for one subject, newest first. */
    public function forSubject(string $type, int $id): array
    {
        return $this->where('subject_type', $type)->where('subject_id', $id)
            ->orderBy('noted_on', 'DESC')->orderBy('noted_time', 'DESC')->orderBy('id', 'DESC')->findAll();
    }

    /** Note counts per subject id for one type: [id => n]. */
    public function countsFor(string $type, array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $out = [];
        foreach ($this->select('subject_id, COUNT(*) AS n')->where('subject_type', $type)->whereIn('subject_id', $ids)->groupBy('subject_id')->findAll() as $r) {
            $out[$r['subject_id']] = (int) $r['n'];
        }

        return $out;
    }
}
