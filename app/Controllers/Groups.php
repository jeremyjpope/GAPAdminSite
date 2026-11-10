<?php

namespace App\Controllers;

use App\Models\GroupMemberModel;
use App\Models\GroupModel;
use App\Models\GroupSetModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Groups extends BaseController
{
    public function index()
    {
        $year = current_year();
        $sets = (new GroupSetModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('sort_order')->orderBy('id')->findAll();
        $students = (new StudentModel())->where('program_year_id', $year['id'] ?? 0)->orderBy('last_name')->findAll();
        $byId     = array_column($students, null, 'id');

        // groups + members per set, for the overview cards
        $groups  = [];
        $members = [];
        if ($sets) {
            foreach ((new GroupModel())->whereIn('group_set_id', array_column($sets, 'id'))->orderBy('sort_order')->orderBy('id')->findAll() as $g) {
                $groups[$g['group_set_id']][] = $g;
            }
            $gids = array_merge(...array_map(fn ($gs) => array_column($gs, 'id'), array_values($groups) ?: [[]]));
            if ($gids) {
                foreach ((new GroupMemberModel())->whereIn('group_id', $gids)->findAll() as $m) {
                    if (isset($byId[$m['student_id']])) {
                        $members[$m['group_id']][] = $byId[$m['student_id']];
                    }
                }
            }
        }

        return view('groups/index', ['title' => 'Groups', 'year' => $year, 'sets' => $sets, 'groups' => $groups, 'members' => $members, 'studentCount' => count($students)]);
    }

    public function saveSet()
    {
        $m  = new GroupSetModel();
        $id = (int) $this->request->getPost('id');
        $data = [
            'name'        => trim((string) $this->request->getPost('name')),
            'kind'        => $this->request->getPost('kind') === 'partners' ? 'partners' : 'group',
            'group_label' => trim((string) $this->request->getPost('group_label')) ?: 'Group',
            'notes'       => trim((string) $this->request->getPost('notes')) ?: null,
            'sort_order'  => (int) $this->request->getPost('sort_order'),
        ];
        if ($data['name'] === '') {
            return redirect()->back()->with('error', 'Give the set a name.');
        }
        if ($id) {
            $m->update($id, $data);
        } else {
            $data['program_year_id'] = current_year()['id'];
            $m->insert($data);
            $id = $m->getInsertID();
        }

        return redirect()->to('/groups/sets/' . $id)->with('success', 'Saved.');
    }

    public function deleteSet(int $id)
    {
        $gm  = new GroupModel();
        $ids = array_column($gm->where('group_set_id', $id)->findAll(), 'id');
        if ($ids) {
            (new GroupMemberModel())->whereIn('group_id', $ids)->delete();
        }
        $gm->where('group_set_id', $id)->delete();
        (new GroupSetModel())->delete($id);

        return redirect()->to('/groups')->with('success', 'Set removed.');
    }

    public function set(int $id)
    {
        [$set, $groups, $members, $students, $assignment] = $this->load($id);

        return view('groups/set', ['title' => $set['name'], 'set' => $set, 'groups' => $groups, 'members' => $members, 'students' => $students, 'assignment' => $assignment]);
    }

    public function printSet(int $id)
    {
        [$set, $groups, $members, $students, $assignment] = $this->load($id);

        return view('groups/print', ['set' => $set, 'groups' => $groups, 'members' => $members, 'year' => current_year()]);
    }

    public function saveGroup(int $setId)
    {
        $gm  = new GroupModel();
        $gid = (int) $this->request->getPost('id');
        $data = ['name' => trim((string) $this->request->getPost('name')), 'sort_order' => (int) $this->request->getPost('sort_order'), 'notes' => trim((string) $this->request->getPost('notes')) ?: null];
        if ($data['name'] === '') {
            return redirect()->back()->with('error', 'Give the group a name.');
        }
        if ($gid) {
            $gm->where('group_set_id', $setId)->update($gid, $data);
        } else {
            $data['group_set_id'] = $setId;
            if (! $data['sort_order']) {
                $data['sort_order'] = (int) ($gm->where('group_set_id', $setId)->selectMax('sort_order')->first()['sort_order'] ?? 0) + 10;
            }
            $gm->insert($data);
        }

        return redirect()->to('/groups/sets/' . $setId)->with('success', 'Group saved.');
    }

    public function deleteGroup(int $setId, int $gid)
    {
        (new GroupMemberModel())->where('group_id', $gid)->delete();
        (new GroupModel())->where('group_set_id', $setId)->delete($gid);

        return redirect()->to('/groups/sets/' . $setId)->with('success', 'Group removed.');
    }

    /** Move one student to a group within the set (group_id = 0 unassigns). A student is in at most one group per set. */
    public function assign(int $setId)
    {
        $sid = (int) $this->request->getPost('student_id');
        $gid = (int) $this->request->getPost('group_id');
        $gm  = new GroupModel();
        $ids = array_column($gm->where('group_set_id', $setId)->findAll(), 'id');
        $mm  = new GroupMemberModel();
        if ($ids) {
            $mm->whereIn('group_id', $ids)->where('student_id', $sid)->delete();
        }
        if ($gid && in_array($gid, $ids, false)) {
            $mm->insert(['group_id' => $gid, 'student_id' => $sid]);
        }

        return redirect()->to('/groups/sets/' . $setId)->with('success', 'Assignment saved.');
    }

    private function load(int $id): array
    {
        $set = (new GroupSetModel())->find($id);
        if (! $set) {
            throw PageNotFoundException::forPageNotFound();
        }
        $groups   = (new GroupModel())->where('group_set_id', $id)->orderBy('sort_order')->orderBy('id')->findAll();
        $students = (new StudentModel())->where('program_year_id', $set['program_year_id'])->orderBy('last_name')->orderBy('first_name')->findAll();
        $byId     = array_column($students, null, 'id');
        $members  = [];
        $assignment = []; // student_id => group_id
        if ($groups) {
            foreach ((new GroupMemberModel())->whereIn('group_id', array_column($groups, 'id'))->findAll() as $m) {
                if (isset($byId[$m['student_id']])) {
                    $members[$m['group_id']][] = $byId[$m['student_id']];
                    $assignment[$m['student_id']] = $m['group_id'];
                }
            }
        }

        return [$set, $groups, $members, $students, $assignment];
    }
}
