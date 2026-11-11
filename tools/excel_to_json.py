#!/usr/bin/env python3
"""
Convert a yearly "GAP Database" Excel workbook into JSON for `php spark gap:import`.

Usage:
    python3 tools/excel_to_json.py "<workbook.xlsx>" writable/import/yearN.json [2026-27]

The third argument is the program-year label; it fixes which calendar years undated
payments and "Fall"/"Spring" belong to. If omitted it is guessed from the workbook name.

The workbooks differ from year to year, so this reads columns by header name and finds
sections by their titles rather than by fixed cell addresses. Supported tabs (any may be
missing): "Student Names & Email Addresses", "Applicants" or "Guys"+"Girls",
"Student Auto Information", "Tuition Info", "Host Home Assignments", "Mentor Information",
"Groups Teams", "Volunteer List", "GAP 360 for Women Invitees", "Volunteer Teams".

Requires: pip install openpyxl. Output contains personal data; writable/import/ is git-ignored.
"""
import difflib
import json
import os
import re
import sys
from datetime import date, datetime

import openpyxl

MONTHS = ['July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March', 'April']
MONTH_NUM = {m: n for m, n in zip(MONTHS, [7, 8, 9, 10, 11, 12, 1, 2, 3, 4])}
TITLES = ['St.', 'Van', 'De', 'La', 'Mc', 'Mac', 'Von', 'Del']
HONORIFICS = ('dr', 'mr', 'mrs', 'ms', 'rev', 'pastor')


def s(v):
    if v is None:
        return ''
    if isinstance(v, (datetime, date)):
        return v.strftime('%Y-%m-%d')
    return str(v).strip()


def norm(h):
    return re.sub(r'[^a-z0-9]', '', str(h or '').lower())


def header_map(ws, row=1):
    return {norm(c.value): i for i, c in enumerate(ws[row]) if c.value}


def col(hm, *names):
    """Column index for the first header that matches (exact, then prefix)."""
    for n in names:
        k = norm(n)
        if k in hm:
            return hm[k]
    for n in names:
        k = norm(n)
        for h, i in hm.items():
            if h.startswith(k):
                return i
    return None


def cell(r, i):
    return s(r[i]) if i is not None and i < len(r) else ''


def header_all(row_vals):
    """norm(header) -> [indexes] for a header row (handles repeated headers like two 'Email' columns)."""
    out = {}
    for i, v in enumerate(row_vals):
        if s(v):
            out.setdefault(norm(v), []).append(i)
    return out


def find_sheet(wb, *patterns, exclude=r'copy of'):
    for pat in patterns:
        for n in wb.sheetnames:
            if re.search(pat, n, re.I) and not re.search(exclude, n, re.I):
                return n
    return None


ROLE_ALIASES = {'leadership': 'Leadership Team', 'leadership team': 'Leadership Team', 'leadership team/advisory': 'Leadership Team',
                'advisory': 'Advisory Team', 'advisory team': 'Advisory Team',
                'mentor': 'Guys Mentor', 'host home': 'Host Family', 'host': 'Host Family', 'sub host family': 'Sub Host Family',
                'career': 'Career Moment', 'special topics': 'Special Topic', 'meal': 'Meal Provider'}


def norm_role(role):
    r = role.strip()
    return ROLE_ALIASES.get(r.lower(), r)


def split_person(name):
    """'Jeff St. Romain' -> ('Jeff', 'St. Romain'); 'Evan Midgette Sr.' -> ('Evan', 'Midgette Sr.'); strips Dr./Mr."""
    toks = [t for t in re.sub(r'\s+', ' ', name.replace('"', ' ')).strip().split(' ') if t]
    while toks and toks[0].rstrip('.').lower() in HONORIFICS:
        toks.pop(0)
    if not toks:
        return '', ''
    if len(toks) == 1:
        return toks[0], ''
    last = toks.pop()
    if toks and last.rstrip('.').lower() in ('sr', 'jr', 'ii', 'iii'):
        last = toks.pop() + ' ' + last
    if len(toks) > 1 and (toks[-1] in TITLES or toks[-1].endswith('.')):
        last = toks.pop() + ' ' + last
    return ' '.join(toks), last


def legal_and_preferred(raw):
    """'Joshua (Josh)' / 'Isa(Isabella)' / 'William/Liam' / 'William "Liam"' -> (legal, preferred)."""
    raw = raw.strip()
    m = re.match(r'^\s*([^()/"]+?)\s*[(/"]\s*([^)/"]+?)\s*[)"]?\s*$', raw)
    if not m:
        return raw, ''
    a, b = m.group(1).strip(), m.group(2).strip()
    return (a, b) if len(a) >= len(b) else (b, a)


def parse_date(txt, default_year=None):
    m = re.search(r'(\d{1,2})[/.](\d{1,2})[/.](\d{2,4})', txt)
    if m:
        mo, d, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
        y = y + 2000 if y < 100 else y
        try:
            return date(y, mo, d).isoformat()
        except ValueError:
            return None
    m = re.search(r'(\d{1,2})/(\d{1,2})(?![\d/])', txt)
    if m and default_year:
        mo = int(m.group(1))
        try:
            return date(default_year(mo), mo, int(m.group(2))).isoformat()
        except (ValueError, TypeError):
            return None
    return None


def main(xlsx, out, label=None):
    wb = openpyxl.load_workbook(xlsx, data_only=True)
    warnings = []

    label = label or (re.search(r'(20\d\d)-(\d\d)', os.path.basename(xlsx)) or [None])[0]
    start_year = int(label[:4]) if label else date.today().year
    end_year = start_year + 1

    def year_for_month(mo):            # program year runs Jul/Aug -> May
        return start_year if mo >= 6 else end_year

    # ------------------------------------------------------------------ students
    students = {}      # key -> dict
    order = []

    def new_student(first, last, email=''):
        k = (norm(last), norm(first)[:2])
        st = dict(first_name=first.strip(), last_name=last.strip(), preferred_name='', email=email.lower(), phone='',
                  gender='', birthday=None, address='', tshirt_size='', placement_preference='', allergies_medical='',
                  cleared_to_drive='', auto_insurance='', housing_preference='', notes=[], contacts=[], vehicles=[],
                  host_home=None, mentor=None, tuition=None, mom_email='', dad_email='', _tuition_amount=None)
        students[k] = st
        order.append(k)
        return st

    def find_by_last(last):
        """Match a bare last name ('Mullins', 'McIlory') to exactly one roster student."""
        cands = [st for st in students.values() if norm(st['last_name']) == norm(last)]
        if not cands:
            close = difflib.get_close_matches(norm(last), {norm(st['last_name']) for st in students.values()}, n=1, cutoff=0.75)
            cands = [st for st in students.values() if close and norm(st['last_name']) == close[0]]
        if len(cands) == 1:
            return cands[0]
        warnings.append(f'Could not match last name "{last}" to one student')
        return None

    def find(name, create=False, quiet=False):
        """Match a free-text name to a roster student: by last name (exact, then fuzzy), then first/preferred."""
        raw = name.strip()
        if not raw:
            return None
        legal, pref = legal_and_preferred(raw)
        first, last = split_person(legal)
        firsts = [x for x in (first.split()[0] if first else '', pref) if x]
        cands = list(students.values())
        if last:
            exact = [st for st in cands if norm(st['last_name']) == norm(last)]
            if not exact:
                pool = {norm(st['last_name']) for st in cands}
                close = difflib.get_close_matches(norm(last), pool, n=1, cutoff=0.8)
                if not close and firsts:   # looser spelling allowed when the first name agrees
                    for c_ in difflib.get_close_matches(norm(last), pool, n=3, cutoff=0.65):
                        if any(st for st in cands if norm(st['last_name']) == c_ and any(f and (st['first_name'].lower().startswith(f.lower()) or st['preferred_name'].lower() == f.lower()) for f in firsts)):
                            close = [c_]; break
                exact = [st for st in cands if close and norm(st['last_name']) == close[0]]
                if exact and not quiet:
                    warnings.append(f'"{raw}" matched {exact[0]["first_name"]} {exact[0]["last_name"]} by similar last name')
            cands = exact
        else:
            cands = [st for st in cands if any(f and (st['first_name'].lower().startswith(f.lower()) or st['preferred_name'].lower() == f.lower()) for f in firsts)]
            if len(cands) == 1 and not quiet:
                warnings.append(f'Matched "{raw}" to {cands[0]["first_name"]} {cands[0]["last_name"]} by first name only')
        if len(cands) > 1 and firsts:
            narrowed = [st for st in cands if any(f and (st['first_name'].lower().startswith(f.lower()) or f.lower().startswith(st['first_name'].lower()) or st['preferred_name'].lower() == f.lower()) for f in firsts)]
            cands = narrowed or cands
        if len(cands) == 1:
            return cands[0]
        if create and last:
            st = new_student(first or firsts[0], last)
            st['preferred_name'] = pref
            return st
        if not quiet:
            warnings.append(f'Could not match "{raw}" to a student' + (' (ambiguous)' if len(cands) > 1 else ''))
        return None

    # 1. Roster. Sources, in order: a roster sheet; group-list sheets (Group | First | Last ...);
    #    a "2019 Students"-style sheet; a "Guy Directory"; else names in the housing + mentor sheets.
    group_list_sets = []     # [(set name, {group: [names]})] collected from group-list sheets
    hh_sheet = find_sheet(wb, r'^host home assignments$', r'^host homes?$', r'^host home information$')
    mentor_sheet = find_sheet(wb, r'^mentor information$')

    if 'Student Names & Email Addresses' in wb.sheetnames:
        for r in wb['Student Names & Email Addresses'].iter_rows(values_only=True):
            first, last, email = s(r[0]), s(r[1]), s(r[2])
            if first and last:
                new_student(first, last, email)

    def set_name_for(labels):
        prefixes = {re.sub(r'[\s/]*(\b[A-D]\b(/[A-D])?|\d+)\s*$', '', l.replace('\n', ' ')).strip().lower() for l in labels}
        prefixes.discard('')
        if len(prefixes) == 1:
            pfx = prefixes.pop()
            return {'pres': 'Team Presentation Groups', 'coed': 'Coed Groups', 'girls': 'Girls Groups',
                    'guys': 'Guys Groups', 'boys': 'Guys Groups', 'group': 'Student Groups'}.get(pfx, pfx.title() + ' Groups')
        return 'Student Groups'

    for gl_name in [n for n in wb.sheetnames if re.search(r'groups$', n, re.I) and not re.search(r'groups ?teams', n, re.I)]:
        ws = wb[gl_name]
        rows = [[s(v) for v in r] for r in ws.iter_rows(values_only=True)]
        hdr = None
        blocks, cur_block, cur_group = [], None, None
        for r in rows:
            if not any(r):
                if cur_block:
                    blocks.append(cur_block); cur_block = None
                continue
            if r[0].lower() == 'group' and any(x.lower() in ('first', 'name', 'first name') for x in r):
                hdr = header_all(r)
                if cur_block:
                    blocks.append(cur_block)
                cur_block, cur_group = {'groups': {}, 'hdr': hdr}, None
                continue
            if not hdr:
                continue
            if cur_block is None:
                cur_block, cur_group = {'groups': {}, 'hdr': hdr}, None
            def c(*names):
                for n in names:
                    if norm(n) in hdr:
                        return r[hdr[norm(n)][0]] if hdr[norm(n)][0] < len(r) else ''
                return ''
            g = r[0].replace('\n', ' ').strip()
            if g:
                cur_group = g
            if norm('first') in hdr or norm('first name') in hdr:
                first, last = c('First', 'First Name'), c('Last', 'Last Name')
                full = f'{first} {last}'.strip()
            else:
                full = re.sub(r'^\(\*?[A-D]\)\s*', '', c('Name')).strip()
            if not full or len(full.split()) < 2 or cur_group is None:
                continue
            st = find(full, create=not blocks, quiet=True)      # only the first block may add roster names
            if not st:
                continue
            email, phone = c('Email').lower(), c('Phone Number', 'Phone')
            st['email'] = st['email'] or email
            st['phone'] = st['phone'] or phone
            st['address'] = st['address'] or c('HomeTown')
            hh = c('Host Home')
            if hh and hh.lower() not in ('home', 'rental', 'parent', 'parents', 'parent/grandparent', 'tbd', 'n/a'):
                st.setdefault('_hh_text', hh)
            elif hh:
                st['_at_home'] = True
            m_ = c('Student Mentor', 'Mentor')
            if m_:
                st['mentor'] = st['mentor'] or m_
            sponsor = c('Host Home Sponsor')
            if sponsor:
                st['notes'].append('Host home sponsor: ' + sponsor)
            cur_block['groups'].setdefault(cur_group, []).append(f"{st['first_name']} {st['last_name']}")
        if cur_block:
            blocks.append(cur_block)
        for b in blocks:
            if b['groups'] and sum(len(v) for v in b['groups'].values()) >= 2:
                nm = set_name_for(list(b['groups'].keys()))
                existing = next((g for n_, g in group_list_sets if n_ == nm), None)
                if existing is not None:
                    for k, v in b['groups'].items():
                        existing.setdefault(k, []).extend(x for x in v if x not in existing.get(k, []))
                else:
                    group_list_sets.append((nm, b['groups']))

    students_sheet = find_sheet(wb, r'^\d{4} students$')
    if students_sheet:
        ws = wb[students_sheet]
        hm = header_map(ws)
        c_name, c_email, c_cell = col(hm, 'GAP Student Name', 'Student Name', 'Name'), col(hm, 'Email'), col(hm, 'Cell', 'Phone')
        c_par, c_paremail = col(hm, 'Parents'), col(hm, "Parent's Email")
        c_paid = next((i for h, i in hm.items() if h.startswith('datepaid')), None)
        paid_hdr = next((s(c_.value) for c_ in ws[1] if norm(c_.value).startswith('datepaid')), '')
        tuition_total = float(re.sub(r'[^\d.]', '', paid_hdr) or 0) or None
        for r in ws.iter_rows(min_row=2, values_only=True):
            full = cell(r, c_name)
            if not full or len(full.split()) < 2:
                continue
            first, last = split_person(full)
            st = new_student(first, last, cell(r, c_email))
            st['phone'] = cell(r, c_cell)
            par = cell(r, c_par)
            if par and not par.lower().startswith('friend'):
                pfirst_parts = [x.strip() for x in re.split(r'\s*(?:&|\band\b)\s*', par) if x.strip()]
                plast = split_person(pfirst_parts[-1])[1] if pfirst_parts else ''
                for i, pp in enumerate(pfirst_parts):
                    f_, l_ = split_person(pp)
                    st['contacts'].append(dict(name=f'{f_} {l_ or plast}'.strip(), email=cell(r, c_paremail) if i == 0 else '', phone='', relation='Parent'))
            elif par:
                st['contacts'].append(dict(name=par, email=cell(r, c_paremail), phone='', relation='Emergency'))
            paid = cell(r, c_paid)
            if tuition_total:
                pays = []
                if re.match(r'\d{4}-\d\d-\d\d', paid):
                    pays.append(dict(paid_on=paid[:10], amount=tuition_total, method='other', reference='', note='Imported: ' + paid_hdr))
                st['tuition'] = dict(plan_type='year', total_due=tuition_total, notes=(paid_hdr + ': ' + paid) if paid else '', payments=pays)
                if paid and not pays:
                    st['notes'].append(paid_hdr + ': ' + paid)

    directory = find_sheet(wb, r'guy directory$', r'girl directory$')
    if directory and not students:
        ws = wb[directory]
        gender = 'M' if 'guy' in directory.lower() else 'F'
        hm = None
        for r in ws.iter_rows(values_only=True):
            vals = [s(v) for v in r]
            if hm is None:
                if 'name' in [v.lower() for v in vals]:
                    hm = header_all(vals)
                continue
            full = vals[hm['name'][0]] if 'name' in hm else ''
            if not full or len(full.split()) < 2:
                continue
            first, last = split_person(full)
            st = new_student(first, last, vals[hm['email'][0]] if 'email' in hm else '')
            st['gender'] = gender
            st['phone'] = vals[hm['phone'][0]] if 'phone' in hm else ''
            st['address'] = (vals[hm['homeaddress'][0]] if 'homeaddress' in hm else '').replace('\n', ', ')
            bd = vals[hm['birthday'][0]] if 'birthday' in hm else ''
            st['birthday'] = bd[:10] if re.match(r'\d{4}-\d\d-\d\d', bd) else None

    if not students:
        if hh_sheet:
            for r in wb[hh_sheet].iter_rows(values_only=True):
                a, b = s(r[0]), s(r[1])
                if a and '@' in b and a not in ('Name', 'Student'):
                    st = find(a, create=True, quiet=True)
                    if st and not st['email']:
                        st['email'] = b.lower()
        if mentor_sheet:
            for r in wb[mentor_sheet].iter_rows(values_only=True):
                a, b, c3 = s(r[0]), s(r[1]), s(r[2])
                if a == 'Timeslot' or b == 'Position':
                    break
                if a and b and '@' in c3 and a != 'Student' and not a.isupper() and len(a.split()) >= 2:
                    find(a, create=True, quiet=True)

    # 2. Applicant details ("Applicants" with GUY/GIRL sections, or "Guys" + "Girls" tabs)
    def apply_applicants(ws, fixed_gender=''):
        hm = header_map(ws)
        c_first, c_last = col(hm, 'First Name'), col(hm, 'Last Name')
        if c_first is None or c_last is None:
            return
        c = dict(email=col(hm, 'Email Address', 'Email'), phone=col(hm, 'Phone Number', 'Phone'),
                 address=col(hm, 'Address', 'Hometown Address', 'Home Address', 'HomeTown'),
                 tuition=col(hm, 'Yearly Tuition', 'Tuition Payment', 'Spring Tuition Paid'),
                 tshirt=col(hm, 'T-Shirt Size'), place=col(hm, 'Preschool or Elementary Preference', 'Church Serving Preference'),
                 mom=col(hm, "Mom's Email"), dad=col(hm, "Dad's Email"), birthday=col(hm, 'Birthday'),
                 medical=col(hm, 'Allergies?/Medical notes', 'Allergies?'), drive=col(hm, 'Cleared to Drive'),
                 insurance=col(hm, 'Auto Insurance'), vehicle=col(hm, 'Vehicle Information'),
                 housing=col(hm, 'Housing Preference'), commit=col(hm, 'Commitment Letter Signed', 'Commitment Letter received'),
                 applied=col(hm, 'Step 1 : Application'), notes=col(hm, 'Notes', 'NOTES'))
        gender = fixed_gender
        for r in ws.iter_rows(min_row=2, values_only=True):
            rawfirst, last = cell(r, c_first), cell(r, c_last)
            if 'GUY' in rawfirst.upper() and not last:
                gender = 'M'; continue
            if 'GIRL' in rawfirst.upper() and not last:
                gender = 'F'; continue
            if not rawfirst or not last:
                continue
            legal, pref = legal_and_preferred(rawfirst)
            email = cell(r, c['email']).lower()
            st = next((x for x in students.values() if email and x['email'] == email), None) or find(f'{legal} {last}', quiet=True)
            if not st:
                continue   # not an accepted student
            if pref:
                st['first_name'], st['preferred_name'] = legal, pref
            elif legal and legal != st['first_name'] and len(legal) > len(st['first_name']):
                st['preferred_name'] = st['preferred_name'] or st['first_name']
                st['first_name'] = legal
            st['gender'] = st['gender'] or gender
            st['email'] = st['email'] or email
            st['phone'] = st['phone'] or cell(r, c['phone'])
            st['address'] = st['address'] or cell(r, c['address']).replace('\n', ', ')
            st['birthday'] = st['birthday'] or (cell(r, c['birthday'])[:10] or None)
            st['tshirt_size'] = st['tshirt_size'] or cell(r, c['tshirt'])
            st['placement_preference'] = st['placement_preference'] or cell(r, c['place'])
            st['allergies_medical'] = st['allergies_medical'] or cell(r, c['medical'])
            st['cleared_to_drive'] = st['cleared_to_drive'] or cell(r, c['drive'])
            st['housing_preference'] = st['housing_preference'] or cell(r, c['housing'])
            st['mom_email'] = cell(r, c['mom']).lower()
            st['dad_email'] = cell(r, c['dad']).lower()
            # vehicle + insurance: Year 8 keeps a separate sheet; Year 7 packs it into one column
            for key in ('vehicle', 'insurance'):
                txt = cell(r, c[key])
                if not txt:
                    continue
                vin = re.search(r'VIN[:#\s-]*([A-HJ-NPR-Z0-9]{11,17})', txt, re.I)
                segs = [x.strip() for x in re.split(r'[;/]\s*(?=[A-Z0-9])', txt) if x.strip()]
                veh = next((x for x in segs if re.match(r'^(19|20)\d\d\s', x)), '')
                if veh and not any(v['description'] == veh for v in st['vehicles']):
                    st['vehicles'].append(dict(description=veh, vin=vin.group(1).upper() if vin else '', oil_type='', oil_notes=''))
                rest = [x for x in segs if x != veh and not (vin and vin.group(0) in x and len(x) < len(vin.group(0)) + 6)]
                ins = '; '.join(x for x in rest if not re.match(r'^VIN', x, re.I))
                if ins and key == 'insurance' or (ins and 'insur' in ins.lower()):
                    st['auto_insurance'] = st['auto_insurance'] or ins
            tuition = cell(r, c['tuition'])
            if tuition and not st['tuition']:
                if re.fullmatch(r'\d+(\.\d+)?', tuition):
                    st['_tuition_amount'] = float(tuition)
                else:
                    st['_tuition_text'] = tuition
            for lbl, key in (('Commitment letter', 'commit'), ('Application submitted', 'applied')):
                v = cell(r, c[key])
                if v:
                    st['notes'].append(f'{lbl}: {v[:60]}')
            n = cell(r, c['notes'])
            if n:
                st['notes'].append(n)

    app_tab = find_sheet(wb, r'applicants$')
    if app_tab:
        apply_applicants(wb[app_tab])
    for pat, g in ((r'(^|\s)guys?$', 'M'), (r'(^|\s)girls?$', 'F')):
        tab = find_sheet(wb, pat)
        if tab:
            apply_applicants(wb[tab], g)

    # 3. Vehicles sheet (Year 8)
    if 'Student Auto Information' in wb.sheetnames:
        for r in wb['Student Auto Information'].iter_rows(min_row=2, values_only=True):
            if s(r[0]) and s(r[1]):
                st = find(f'{s(r[0])} {s(r[1])}')
                if st and not any(v['description'] == s(r[2]) for v in st['vehicles']):
                    st['vehicles'].append(dict(description=s(r[2]), vin=s(r[3]), oil_type=s(r[4]), oil_notes=s(r[5])))

    # 4. Mentors
    mentors = {}
    mentor_positions = {}   # name -> position text (older workbooks)
    gender = ''
    if mentor_sheet:
        layout = None
        for r in wb[mentor_sheet].iter_rows(values_only=True):
            a, b, c3 = s(r[0]), s(r[1]), s(r[2])
            up = a.upper()
            if 'PREVIOUS' in up:
                break
            if re.search(r"guys'? mentors|girls'? mentors", a, re.I) or (up == a and ('GUYS' in up or 'GIRLS' in up)):
                gender = 'M' if 'GUY' in up else 'F'
                continue
            if a == 'Timeslot' or b == 'Position':
                layout = 'positions'; continue
            if layout == 'positions':
                first, last, email = s(r[2]), s(r[3]), s(r[4])
                if first and last:
                    nm = f'{first} {last}'
                    mentors.setdefault(nm, dict(name=nm, email=email if '@' in email else '', gender=gender))
                    mentor_positions[nm] = b
                continue
            if a == 'Student' or not a or not b:
                continue
            email = c3 if '@' in c3 and ' ' not in c3 else ''
            if c3 and not email:
                warnings.append(f'Mentor email looks malformed for {b}: "{c3}"')
            mentors.setdefault(b, dict(name=b, email=email, gender=gender))
            st = find(a)
            if st:
                st['mentor'] = b

    # 5. Host homes + parents (header-driven: each section starts with a header row)
    homes = {}
    if hh_sheet:
        ws = wb[hh_sheet]
        section, current, last_home, hdr = 'HOST', None, None, None
        started = not re.search(r'potential host families', s(ws['A1'].value) + s(ws['B1'].value), re.I)

        def hget(r, *names, nth=0):
            for n in names:
                k = norm(n)
                if k in hdr and len(hdr[k]) > nth:
                    i = hdr[k][nth]
                    return s(r[i]) if i < len(r) else ''
            return ''

        for r in ws.iter_rows(values_only=True):
            vals = [s(v) for v in r]
            a = vals[0] if vals else ''
            up = a.upper()
            low = [v.lower() for v in vals]
            if a.lower() in ('student', 'name', 'gap student', 'student assignment', '#') and ('email' in low or 'host home email' in low or 'name' in low[1:]):
                hdr = header_all(vals); started = True; current = None; continue
            if not started:
                if re.search(r'student housing arrangements|committed host home', ' '.join(vals), re.I):
                    started = True
                continue
            if up.startswith('LIVING AT HOME'):
                section, current, last_home = 'HOME', None, None; continue
            if up.startswith('STUDENT ACQUIRED'):
                section, current, last_home = 'OWN', None, None; continue
            if up.startswith(('FRIDAY MEAL', 'SUB HOST', 'POTENTIAL', 'WEEKLY FAMILY', 'CURRENT HOST')) or re.search(r'sub host|potential host', ' '.join(vals[:3]), re.I):
                break
            if hdr is None or not any(vals):
                continue
            if 'studentassignment' in hdr:
                stu_txt, host_txt = hget(r, 'Student Assignment'), hget(r, 'Name')
                hh_addr, hh_email, hh_phone, mentor_txt = hget(r, 'Address'), hget(r, 'Email'), hget(r, 'Phone'), ''
                par_names = par_emails = par_phones = stu_email = stu_phone = home_addr = ''
            elif 'gapstudent' in hdr:
                stu_txt, host_txt = hget(r, 'GAP Student'), hget(r, 'Host Homes')
                hh_addr, hh_email, hh_phone, mentor_txt = hget(r, 'Host Home Address'), hget(r, 'Host Home Email'), hget(r, 'Cell Phone'), ''
                par_names = par_emails = par_phones = stu_email = stu_phone = home_addr = ''
            else:
                stu_txt = hget(r, 'Student', 'Name')
                stu_email, stu_phone = hget(r, 'Email'), hget(r, 'Phone')
                home_addr = hget(r, 'Home Address')
                par_names, par_emails, par_phones = hget(r, "Parent's Names"), hget(r, "Parent's Emails"), hget(r, "Parent's Phones")
                host_txt = hget(r, 'Host Home (HH)', "Host's Names", 'Host Family', 'Host Home')
                hh_addr = hget(r, 'HH Address', "Host's Address", 'Address')
                csz = hget(r, 'HH CSZ', 'CSZ')
                if csz:
                    hh_addr = (hh_addr + ', ' + csz).strip(', ')
                hh_email = hget(r, 'HH Email', "Host's Email") or (hget(r, 'Email', nth=1) if len(hdr.get('email', [])) > 1 else '')
                hh_phone = hget(r, 'HH Phone', "Host's Phone") or (hget(r, 'Phone', nth=1) if len(hdr.get('phone', [])) > 1 else '')
                mentor_txt = hget(r, 'Mentor', 'Student Mentor')
                if not par_emails:
                    par_emails = hget(r, "Dad's Email")
                    mom_email = hget(r, "Mom's Email")
                    mom_phone, dad_phone = hget(r, "Mom's Cell Phone"), hget(r, "Dad's Cell Phone")
                else:
                    mom_email = mom_phone = dad_phone = ''
            if host_txt and (host_txt.lower().startswith('requested') or host_txt.lower() in ('host home (hh)', "host's names", 'home', 'tbd')):
                host_txt = ''
            is_student_row = bool(stu_txt) and not stu_txt.lower().startswith('mentor:') and not stu_txt.lower() in ('substitute', 'spring semester availability')
            if is_student_row and (stu_email or 'studentassignment' in hdr or 'gapstudent' in hdr):
                names = [x.strip() for x in re.split(r'\s*(?:&|\band\b)\s*', re.sub(r'\(.*?\)', '', stu_txt)) if x.strip()]
                found = [find(n, quiet=True) for n in names]
                found = [f for f in found if f]
                if not found and stu_email:
                    st_ = find(stu_txt, create=True, quiet=True)
                    found = [st_] if st_ else []
                if not found:
                    current = None; continue
                current = found[0]
                for stx in found:
                    stx['email'] = stx['email'] or stu_email.lower()
                    stx['phone'] = stx['phone'] or stu_phone
                    stx['address'] = stx['address'] or home_addr.replace('\n', ', ')
                    if mentor_txt:
                        stx['mentor'] = stx['mentor'] or mentor_txt
                    if par_names:
                        pp = [x.strip() for x in re.split(r'\s*(?:&|\band\b)\s*', par_names) if x.strip()]
                        plast = split_person(pp[-1])[1] if pp else ''
                        for i_, nm in enumerate(pp):
                            f_, l_ = split_person(nm)
                            stx['contacts'].append(dict(name=f'{f_} {l_ or plast}'.strip(), email=(par_emails if i_ == 0 else (mom_email if 'momsemail' in hdr else '')), phone=(par_phones if i_ == 0 else '')))
                    if 'momsemail' in hdr and mom_email and not any(c_['email'] == mom_email for c_ in stx['contacts']):
                        stx['contacts'].append(dict(name='', email=mom_email, phone=mom_phone))
                if section == 'HOME':
                    current['host_home'] = None; last_home = None
                    continue
                if host_txt and all(norm(split_person(host_txt.split('&')[-1].strip())[1]) == norm(stx['last_name']) for stx in found) and section != 'OWN':
                    for stx in found:
                        stx['notes'].append('Living with family: ' + host_txt)
                    host_txt = ''
                    last_home = None
                if host_txt and all(stx.get('_at_home') for stx in found):
                    for stx in found:
                        stx['notes'].append('Sponsor / meal family: ' + host_txt)
                    last_home = None
                    continue
                if host_txt and all(stx['host_home'] for stx in found):
                    current = None
                    for stx in found:
                        stx['notes'].append('Also housed with ' + host_txt + (' (' + re.sub(r'[()]', '', re.search(r'\(.*?\)', s(r[hdr.get('gapstudent', hdr.get('student', [0]))[0]])).group(0)) + ')' if re.search(r'\(.*?\)', s(r[hdr.get('gapstudent', hdr.get('student', [0]))[0]])) else ''))
                    continue
                if host_txt or (section == 'HOST' and last_home and hh_email):
                    if host_txt:
                        clean = re.sub(r'^\s*renting\s+from\s+', '', host_txt, flags=re.I).strip()
                        same = last_home and not hh_addr and (homes[last_home]['_last'] == split_person(clean)[1] or hh_email and hh_email.lower() in [c_['email'].lower() for c_ in homes[last_home]['contacts']])
                        if same:
                            if clean not in homes[last_home]['family_name']:
                                homes[last_home]['family_name'] += ' & ' + clean
                        else:
                            note = '' if section == 'HOST' else ('Student-acquired home (renting)' if clean != host_txt else 'Student-acquired home')
                            homes.setdefault(clean, dict(key=clean, family_name=clean, address=hh_addr.replace('\n', ', ').split(', , ')[0], notes=note, contacts=[], _last=split_person(clean)[1]))
                            last_home = clean
                    home = homes[last_home]
                    home['contacts'].append(dict(email=hh_email, phone=hh_phone))
                    for stx in found:
                        stx['host_home'] = last_home
                continue
            if stu_txt and not stu_txt.lower().startswith('mentor:'):
                current = None          # a labelled row that is not a student (Substitute, Spring availability, ...)
                continue
            if current is None:
                continue
            if not stu_txt and host_txt and hh_addr and current['host_home'] and section != 'OWN':
                current = None          # a new family with its own address and no student: not a spouse row
                continue
            # continuation row: second parent / second host contact / "Mentor: X"
            if stu_txt.lower().startswith('mentor:'):
                current['mentor'] = current['mentor'] or stu_txt.split(':', 1)[1].strip()
                addr_ = home_addr or (stu_email if stu_email and '@' not in stu_email else '')
                if addr_ and not current['address']:
                    current['address'] = addr_.replace('\n', ', ')
            if par_names:
                current['contacts'].append(dict(name=par_names, email=par_emails, phone=par_phones))
            elif par_emails or par_phones:
                blank = next((c_ for c_ in current['contacts'] if not c_['email'] and c_.get('name')), None)
                if blank:
                    blank['email'], blank['phone'] = par_emails, blank['phone'] or par_phones
                else:
                    current['contacts'].append(dict(name='', email=par_emails, phone=par_phones))
            if not current['host_home'] and host_txt and section == 'OWN':
                homes.setdefault(host_txt, dict(key=host_txt, family_name=host_txt, address=hh_addr.replace('\n', ', '), notes='Student-acquired home', contacts=[], _last=split_person(host_txt)[1]))
                current['host_home'] = host_txt
                homes[host_txt]['contacts'].append(dict(email=hh_email, phone=hh_phone))
                continue
            if current['host_home'] and (host_txt or hh_email or hh_phone):
                home = homes[current['host_home']]
                if host_txt and (section == 'OWN' or 'studentassignment' in hdr or 'gapstudent' in hdr) and host_txt not in home['family_name']:
                    home['family_name'] += ' & ' + host_txt
                if hh_email or hh_phone:
                    home['contacts'].append(dict(email=hh_email, phone=hh_phone))
    # group-list host text for students the housing sheet did not place
    for st in students.values():
        txt = st.pop('_hh_text', '')
        if txt and not st['host_home']:
            key = next((k for k in homes if norm(split_person(k.split('/')[0])[1] or k) == norm(txt.split('/')[-1].split()[-1])), None)
            if not key:
                key = txt.replace('/', ' & ')
                homes[key] = dict(key=key, family_name=key, address='', notes='', contacts=[], _last=split_person(key.split('&')[-1].strip())[1])
            st['host_home'] = key
    for h in homes.values():
        h.pop('_last', None)
    if app_tab:
        apply_applicants(wb[app_tab])
    for pat, g in ((r'(^|\s)guys?$', 'M'), (r'(^|\s)girls?$', 'F')):
        tab = find_sheet(wb, pat)
        if tab:
            apply_applicants(wb[tab], g)
    for st in students.values():
        for ct in st['contacts']:
            if not ct.get('name'):
                ct['name'] = ct['email'] or 'Parent'
    for st in students.values():
        for ct in st['contacts']:
            em = ct['email'].lower()
            ct['relation'] = 'Mother' if em and em == st['mom_email'] else 'Father' if em and em == st['dad_email'] else 'Parent'
        if st['mentor'] and st['mentor'] not in mentors:
            mentors[st['mentor']] = dict(name=st['mentor'], email='', gender='')

    # 6. Tuition: a "Tuition Info" sheet (Year 8) or the applicants' "Tuition Payment" text (Year 7)
    def plan_from_text(txt):
        t = txt.lower()
        if 'month' in t:
            return 'month'
        if 'semester' in t or re.search(r'\b(fall|spring)\b', t):
            return 'semester'
        return 'year' if ('year' in t or 'in full' in t) else 'other'

    if 'Tuition Info' in wb.sheetnames:
        ws = wb['Tuition Info']
        for r in ws.iter_rows(min_row=3, values_only=True):
            name, plan, sent = s(r[0]), s(r[1]), s(r[2])
            if name == 'Invoice_ID':
                break
            if not name or re.fullmatch(r'\d+', name):
                continue
            st = find(name)
            if not st:
                continue
            plan_type = plan_from_text(plan)
            m = re.search(r'\$(\d[\d,]*)', plan)
            amt = float(m.group(1).replace(',', '')) if m else None
            total = st['_tuition_amount'] or (amt and (amt * 10 if plan_type == 'month' else amt * 2 if plan_type == 'semester' else amt))
            payments = []
            if 'PAID' in plan.upper() and plan_type in ('year', 'semester'):
                paid_amt = amt or (total if plan_type == 'year' else (total / 2 if total else None))
                if paid_amt:
                    payments.append(dict(paid_on=parse_date(sent) or f'{start_year}-07-15', amount=paid_amt, method='online', reference='',
                                         note=f'Imported: plan marked PAID ("{plan}"). Date is the invoice-sent date; adjust if needed.'))
            if 'PAID' in plan.upper() and plan_type == 'month':
                warnings.append(f'{name}: monthly plan marked PAID but no amounts in month grid; no payments imported')
            for idx, month in enumerate(MONTHS):
                c_txt = s(r[3 + idx]) if 3 + idx < len(r) else ''
                if not c_txt:
                    continue
                m = re.search(r'\$\s?(\d[\d,]*(?:\.\d\d)?)', c_txt)
                if not m:
                    warnings.append(f'{name}: {month} cell has no $ amount, skipped: "{c_txt}"'); continue
                ck = re.search(r'ck\.?\s*#?\s*(\d+)', c_txt, re.I)
                d = parse_date(c_txt, year_for_month) or date(year_for_month(MONTH_NUM[month]), MONTH_NUM[month], 1).isoformat()
                payments.append(dict(paid_on=d, amount=float(m.group(1).replace(',', '')), method='check' if ck else 'other',
                                     reference=f'ck #{ck.group(1)}' if ck else '', note=f'{month}: {c_txt}'))
            st['tuition'] = dict(plan_type=plan_type, total_due=total, notes='; '.join(x for x in (plan, sent) if x), payments=payments)
    # Rate box on the Host Home sheet, when rates depend on housing (Year 7): "$3500" without a GAP home, "$5000" with one
    housing_rates = None
    if 'Host Home Assignments' in wb.sheetnames:
        hdr_txt = ' '.join(s(c_.value) for c_ in wb['Host Home Assignments'][5])
        if 'Host Home' in hdr_txt and 'GAP Tuition' in ' '.join(s(c_.value) for c_ in wb['Host Home Assignments'][4]):
            for r in wb['Host Home Assignments'].iter_rows(min_row=6, max_row=8, values_only=True):
                if s(r[13]).lower() == 'year':
                    a_, b_ = re.search(r'\$(\d+)', s(r[14])), re.search(r'\$(\d+)', s(r[15]))
                    if a_ and b_:
                        housing_rates = (float(a_.group(1)), float(b_.group(1)))
    # Workbook-wide hint when there is no rate box: the usual semester amount implies the year total.
    big_amounts = []
    for st in students.values():
        txt = st.get('_tuition_text', '')
        if txt:
            big_amounts += [float(a.replace(',', '')) for a in re.findall(r'\$\s?(\d[\d,]*)', txt) if float(a.replace(',', '')) >= 1000]
    usual_total = None
    if big_amounts:
        common = max(set(big_amounts), key=big_amounts.count)
        usual_total = common * 2 if big_amounts.count(common * 2) <= big_amounts.count(common) else common * 2
    for st in students.values():
        txt = st.pop('_tuition_text', '')
        if st['tuition'] or not txt:
            continue
        plan_type = plan_from_text(txt)
        amounts = [float(a.replace(',', '')) for a in re.findall(r'\$\s?(\d[\d,]*(?:\.\d\d)?)', txt)]
        tier = None
        if not housing_rates and usual_total:
            if plan_type == 'year' and amounts and amounts[0] in (usual_total, usual_total * 10 / 7):
                tier = amounts[0]
            else:
                tier = usual_total
        if housing_rates:
            lo, hi = housing_rates
            if any(a in (hi, hi / 2, hi / 10) for a in amounts):
                tier = hi
            elif any(a in (lo, lo / 2, lo / 10) for a in amounts):
                tier = lo
            else:
                gap_home = st['host_home'] and homes.get(st['host_home'], {}).get('notes', '') == ''
                tier = hi if gap_home else lo
                warnings.append(f'{st["first_name"]} {st["last_name"]}: tuition total assumed {int(tier)} from housing type; check "{txt[:40]}"')
        total = st['_tuition_amount'] or tier
        if plan_type == 'other' and total and amounts:
            first_amt = amounts[0]
            plan_type = 'year' if first_amt == total else 'semester' if first_amt == total / 2 else 'month' if first_amt == total / 10 else 'other'
        payments = []
        cum = 0.0
        for seg in re.split(r';|,(?=[^,]*\$)|\band\b(?=[^;]*\$)|:(?=\s*\$)', txt):
            m = re.search(r'\$\s?(\d[\d,]*(?:\.\d\d)?)', seg)
            if not m:
                continue
            low = seg.lower()
            if 'paid' not in low and 'deposit' not in low and 'check' not in low and 'ck' not in low and 'online' not in low and 'cash' not in low:
                continue
            if re.search(r'scholarship|refund|forgiven|debt', low) and 'deposited' not in low:
                continue
            amt = float(m.group(1).replace(',', ''))
            # "$1050 paid by 10/1" / "$700 paid as of 9/4" are running totals, not new payments
            if re.search(r'\$\s?[\d,]+(?:\.\d\d)?\s*(?:paid\s+)?(?:by|as of)\b', low) or re.search(r'total paid', low):
                inc = amt - cum
                if inc <= 0:
                    continue
                amt = inc
            cum += amt
            method = 'online' if 'online' in low else 'check' if ('check' in low or re.search(r'\bck\b', low)) else 'cash' if 'cash' in low else 'other'
            ck = re.search(r'(?:check|ck)\s*#?\s*(\d+)', seg, re.I)
            d_ = parse_date(seg, year_for_month)
            default_d = f'{end_year}-01-15' if 'spring' in low else f'{start_year}-08-01'
            payments.append(dict(paid_on=d_ or default_d, amount=amt,
                                 method=method, reference=f'ck #{ck.group(1)}' if ck else '',
                                 note='Imported: ' + seg.strip()[:160] + ('' if d_ else ' (date not in sheet; defaulted)')))
        st['tuition'] = dict(plan_type=plan_type, total_due=total, notes=txt[:500], payments=payments)
        if total and sum(p['amount'] for p in payments) > total:
            warnings.append(f'{st["first_name"]} {st["last_name"]}: imported payments ({int(sum(p["amount"] for p in payments))}) exceed the total ({int(total)}); check the ledger')
        if total is None:
            warnings.append(f'{st["first_name"]} {st["last_name"]}: could not tell the tuition total from "{txt[:50]}"')

    # ------------------------------------------------------------------ volunteers
    volunteers = {}

    def vkey(first, last):
        return (norm(first), norm(last))

    def vol_get(first, last, email=''):
        email = (email or '').strip().lower()
        if email:
            for v in volunteers.values():
                if v['email'] and v['email'].lower() == email:
                    return v
        k = vkey(first, last)
        if k not in volunteers:
            volunteers[k] = dict(first_name=first.strip(), last_name=last.strip(), email=email, phone='', address='',
                                 city_state_zip='', gender='', status='active', member_of_dbc='', tshirt_size='',
                                 mp_group_placed_on=None, opportunity_response='', driver_cleared_year=None,
                                 background_check='', saat_training='', notes='', roles=[])
        return volunteers[k]

    def vol_find(first, last):
        v = volunteers.get(vkey(first, last))
        if v:
            return v
        close = difflib.get_close_matches(norm(last), {k[1] for k in volunteers}, n=1, cutoff=0.85)
        if close:
            hits = [v for k, v in volunteers.items() if k[1] == close[0] and (k[0] == norm(first) or k[0].startswith(norm(first)[:3]))]
            if len(hits) == 1:
                return hits[0]
        return None

    def add_role(v, role, category='', lead=None):
        role = role.strip()
        if not role:
            return
        for r in v['roles']:
            if r['role'] == role:
                if category and not r['category']:
                    r['category'] = category
                if lead is not None:
                    r['is_lead'] = lead
                return
        v['roles'].append(dict(role=role, category=category or '', is_lead=bool(lead)))

    def add_note(v, txt):
        if txt and txt not in v['notes']:
            v['notes'] = (v['notes'] + '\n' if v['notes'] else '') + txt

    vol_sheet = find_sheet(wb, r'^volunteer list$', r'^gap volunteer list$')
    if vol_sheet:
        ws = wb[vol_sheet]
        hm = header_map(ws)
        hdr = [s(c.value) for c in ws[1]]
        role_cols = [i for i, h in enumerate(hdr) if norm(h) == 'role']
        c = dict(first=col(hm, 'First Name'), last=col(hm, 'Last Name'), email=col(hm, 'Email'), phone=col(hm, 'Phone'),
                 address=col(hm, 'Address'), csz=col(hm, 'CSZ'), male=col(hm, 'Male'), member=col(hm, 'Member of DBC'),
                 tshirt=col(hm, 'T-shirt Size'), placed=col(hm, 'Placed in Ministry Platform Group'),
                 opp=col(hm, 'Opportunity Response Created'), driver=col(hm, 'Driver Opportunity Complete'),
                 bgc=col(hm, 'Background Check'), saat=col(hm, 'SAAT Training'), cat=col(hm, 'Life Skills Category'), notes=col(hm, 'Notes'))
        driver_year = (re.search(r'(20\d\d)', hdr[c['driver']]) if c['driver'] is not None else None)
        driver_year = int(driver_year.group(1)) if driver_year else start_year
        for r in ws.iter_rows(min_row=2, values_only=True):
            first, last = cell(r, c['first']), cell(r, c['last'])
            if not first or not last:
                continue
            v = vol_get(first, last, cell(r, c['email']))
            v['email'] = v['email'] or cell(r, c['email']).lower()
            v['phone'] = v['phone'] or cell(r, c['phone'])
            v['address'] = v['address'] or cell(r, c['address'])
            v['city_state_zip'] = v['city_state_zip'] or cell(r, c['csz'])
            v['gender'] = 'M' if cell(r, c['male']) in ('1', '1.0', 'yes', 'Yes') else v['gender']
            v['member_of_dbc'] = v['member_of_dbc'] or cell(r, c['member'])
            v['tshirt_size'] = v['tshirt_size'] or cell(r, c['tshirt'])
            placed = cell(r, c['placed'])
            v['mp_group_placed_on'] = v['mp_group_placed_on'] or (placed[:10] if re.match(r'\d{4}-\d\d-\d\d', placed) else None)
            v['opportunity_response'] = v['opportunity_response'] or cell(r, c['opp'])[:190]
            v['background_check'] = v['background_check'] or cell(r, c['bgc'])[:120]
            v['saat_training'] = v['saat_training'] or cell(r, c['saat'])[:120]
            drv = cell(r, c['driver']).lower()
            if drv.startswith('yes') or drv == 'cleared' or re.match(r'cleared', drv):
                v['driver_cleared_year'] = v['driver_cleared_year'] or driver_year
            elif drv:
                add_note(v, 'Driver: ' + cell(r, c['driver']))
            add_note(v, cell(r, c['notes']))
            cat = cell(r, c['cat'])
            for i in role_cols:
                role = cell(r, i)
                if role == 'Meal Provider Guest':
                    add_role(v, 'Meal Provider'); add_role(v, 'Guest'); continue
                if role == 'Career Moment Guest':
                    add_role(v, 'Career Moment'); add_role(v, 'Guest'); continue
                role = norm_role(role)
                add_role(v, role, cat if role in ('Life Skills', 'Fitness') else '')
            if cat and not any(x['role'] in ('Life Skills', 'Fitness') for x in v['roles']):
                add_note(v, 'Life skills category: ' + cat)
            if any(x['role'] == 'Potential Volunteer' for x in v['roles']):
                v['status'] = 'potential'

    master = find_sheet(wb, r'master volunteer list$')
    if master and not vol_sheet:
        ws = wb[master]
        hm = header_map(ws)
        flags = {'Sports/Fitness': ('Fitness', ''), 'Life Skills': ('Life Skills', ''), 'Agriculture': ('Life Skills', 'Agriculture'),
                 'Craftsmanship': ('Life Skills', 'Artisanship'), 'Trades': ('Life Skills', 'Trades'), 'Career/Vocation': ('Career Moment', ''),
                 'Social/Civic': ('Special Topic', ''), 'Outdoors': ('Life Skills', 'Outdoors'), 'Host Home': ('Host Family', ''),
                 'Meal Provider': ('Meal Provider', '')}
        fcols = {k: col(hm, k) for k in flags}
        c = dict(first=col(hm, 'First Name'), last=col(hm, 'Last Name'), email=col(hm, 'Email'), phone=col(hm, 'Phone'), approved=col(hm, 'Approved'), other=col(hm, 'Other'))
        for r in ws.iter_rows(min_row=2, values_only=True):
            first, last = cell(r, c['first']), cell(r, c['last'])
            if first and not last and len(first.split()) >= 2:
                first, last = split_person(first)
            if not first or not last:
                continue
            v = vol_get(first, last, cell(r, c['email']))
            v['email'] = v['email'] or cell(r, c['email']).lower()
            v['phone'] = v['phone'] or cell(r, c['phone'])
            for k, (role, cat) in flags.items():
                if cell(r, fcols[k]) in ('1', '1.0', 'yes', 'Yes', 'x', 'X'):
                    add_role(v, role, cat)
            other = cell(r, c['other'])
            if other and other not in ('0', '0.0'):
                add_note(v, 'Other: ' + other)
            if cell(r, c['approved']).lower() in ('no', 'n'):
                v['status'] = 'potential'

    if 'GAP 360 for Women Invitees' in wb.sheetnames:
        ws = wb['GAP 360 for Women Invitees']
        hm = header_map(ws)
        c = dict(first=col(hm, 'First Name'), last=col(hm, 'Last Name'), email=col(hm, 'Email'), bgc=col(hm, 'Background Check'),
                 saat=col(hm, 'SAAT Training'), tshirt=col(hm, 'T-shirt Size'))
        for r in ws.iter_rows(min_row=2, values_only=True):
            first, last = cell(r, c['first']), cell(r, c['last'])
            if not first or not last:
                continue
            v = vol_find(first, last)
            if not v:
                email = cell(r, c['email']).lower()
                v = next((x for x in volunteers.values() if email and x['email'] == email), None)
            if not v:
                continue
            v['gender'] = v['gender'] or 'F'
            v['background_check'] = v['background_check'] or cell(r, c['bgc'])[:120]
            v['saat_training'] = v['saat_training'] or cell(r, c['saat'])[:120]
            v['tshirt_size'] = v['tshirt_size'] or cell(r, c['tshirt'])

    if 'Volunteer Teams' in wb.sheetnames:
        area = ''
        for r in wb['Volunteer Teams'].iter_rows(min_row=2, values_only=True):
            area = s(r[0]) or area
            lead = s(r[2])
            if not lead:
                continue
            first, last = split_person(lead)
            v = vol_find(first, last) if last else None
            if v:
                add_role(v, 'Life Skills', area, lead=True)
            else:
                warnings.append(f'Volunteer Teams lead "{lead}" not on the Volunteer List')

    for m in mentors.values():
        first, last = split_person(m['name'])
        if last:
            v = (m['email'] and next((x for x in volunteers.values() if x['email'] == m['email'].lower()), None)) or vol_find(first, last) or vol_get(first, last, m['email'])
            v['email'] = v['email'] or m['email'].lower()
            v['gender'] = v['gender'] or m['gender']
            add_role(v, 'Girls Mentor' if m['gender'] == 'F' else 'Guys Mentor')
            if mentor_positions.get(m['name']):
                add_note(v, f"{label}: {mentor_positions[m['name']]}")
            m['name'] = f"{v['first_name']} {v['last_name']}"
    for st in students.values():
        if st['mentor']:
            first, last = split_person(st['mentor'])
            v = vol_find(first, last)
            if v:
                st['mentor'] = f"{v['first_name']} {v['last_name']}"

    for h in homes.values():
        parts = [p.strip() for p in re.split(r'\s*(?:&|\band\b)\s*', h['family_name']) if p.strip()]
        people, lastname = [], ''
        for p in reversed(parts):
            first, last = split_person(p)
            if not last:
                last = lastname
            else:
                lastname = last
            people.insert(0, (first, last))
        contacts = [c3 for c3 in h.pop('contacts') if c3['email'] or c3['phone']]
        seen = []
        for c3 in contacts:
            if c3 not in seen:
                seen.append(c3)
        emails = [c3['email'] for c3 in seen if c3['email']]
        phones = [c3['phone'] for c3 in seen if c3['phone']]
        for i, (first, last) in enumerate(people):
            v = (i < len(emails) and next((x for x in volunteers.values() if x['email'] == emails[i].lower()), None)) or vol_find(first, last) or vol_get(first, last, emails[i] if i < len(emails) else '')
            v['email'] = v['email'] or (emails[i].lower() if i < len(emails) else '')
            v['phone'] = v['phone'] or (phones[i] if i < len(phones) else '')
            v['address'] = v['address'] or h['address']
            add_role(v, 'Host Family')
        h['people'] = [f'{f} {l}'.strip() for f, l in people]
        h['email'], h['email2'] = (emails + ['', ''])[:2]
        h['phone'], h['phone2'] = (phones + ['', ''])[:2]

    # ------------------------------------------------------------------ groups
    group_sets = []
    gsheet = next((n for n in wb.sheetnames if norm(n) == 'groupsteams'), None)
    if gsheet:
        ws = wb[gsheet]
        grid = {}
        for row in ws.iter_rows():
            for c3 in row:
                if s(c3.value):
                    grid[(c3.row, c3.column)] = s(c3.value)
        max_row = ws.max_row

        def full_name(txt):
            st = find(txt)
            return f"{st['first_name']} {st['last_name']}" if st else None

        title_specs = [
            (r'^team presentation', 'Team Presentation Groups', 'Team'),
            (r"children'?s learning", "Fall Children's Learning Center", 'Age group'),
            (r'churchlife', 'Fall ChurchLife', 'Group'),
            (r"^guys'? life skills", "Guys' Life Skills & Friday Night Group", 'Group'),
            (r"^girls'? friday", "Girls' Friday Night Group", 'Group'),
            (r'^accountability', 'Accountability Partners', 'Pair'),
        ]
        titles = []   # (row, col, name, group label)
        for (rr, cc), txt in grid.items():
            for pat, name, glabel in title_specs:
                if re.search(pat, txt, re.I):
                    titles.append((rr, cc, name, glabel))
        titles.sort()

        # Matrix layout: a header row "First | Last | <set> | <set> ...", one row per student.
        matrix_sets = set()
        for (rr, cc), txt in sorted(grid.items()):
            if txt.lower() == 'first' and grid.get((rr, cc + 1), '').lower() == 'last':
                extra = []
                c2 = cc + 2
                while grid.get((rr, c2)):
                    extra.append((c2, grid[(rr, c2)].strip()))
                    c2 += 1
                if len(extra) < 2:
                    continue
                sets = {name: {} for _, name in extra}
                r2 = rr + 1
                while grid.get((r2, cc)) or grid.get((r2, cc + 1)):
                    m = full_name(f"{grid.get((r2, cc), '')} {grid.get((r2, cc + 1), '')}".strip())
                    if m:
                        for c3, name in extra:
                            g = grid.get((r2, c3), '')
                            if g and m not in sets[name].setdefault(g, []):
                                sets[name][g].append(m)
                    r2 += 1
                for _, name in extra:
                    if re.search(r'pre-?arrival|door code|assignment', name, re.I):
                        continue
                    biggest = max((len(v) for v in sets[name].values()), default=0)
                    total_m = sum(len(v) for v in sets[name].values())
                    if sets[name] and total_m and biggest / total_m > 0.9 and len(sets[name]) > 1:
                        continue   # a status column ("done"), not a grouping
                    if sets[name]:
                        label_ = 'Team' if 'team' in name.lower() else 'Group'
                        group_sets.append(dict(name=name, kind='group', group_label=label_, groups=[dict(name=k, members=v) for k, v in sets[name].items()]))
                        matrix_sets.add(norm(name))
                titles = [t for t in titles if not (t[0] >= rr and t[0] < r2)]
        if matrix_sets:
            # the matrix already covers these; drop title-based duplicates (ChurchLife == Sunday Ministry Investment)
            titles = [t for t in titles if not (t[2] == 'Fall ChurchLife' and 'sundayministryinvestment' in matrix_sets)]

        all_titles = list(titles)

        def region_end(r0, c0):
            later = [rr for rr, cc, _, _ in all_titles if rr > r0 and abs(cc - c0) <= 2]
            # also stop at the next header row ("First"/"Last") in this column block
            hdrs_below = [rr for (rr, cc), t in grid.items() if rr > r0 + 1 and abs(cc - c0) <= 3 and t.lower() in ('first', 'last')]
            later += [h for h in hdrs_below]
            return (min(later) - 1) if later else max_row

        for r0, c0, name, glabel in titles:
            end = region_end(r0, c0)
            if name == 'Accountability Partners':
                pairs, n = {}, 0
                for rr in range(r0 + 1, end + 1):
                    cells = [grid.get((rr, cc), '') for cc in range(c0, c0 + 5)]
                    if not cells[0]:
                        continue
                    if cells[0].lower() in ('girls', 'guys', 'girl', 'guy'):
                        continue
                    if re.fullmatch(r'\d+', cells[0]):
                        names = [x for x in cells[1:] if x]
                        gname = cells[0]
                    elif '/' in cells[0] and ' ' not in cells[0].strip():   # 'Benson/Goodenough': last names
                        sts = [find_by_last(x.strip()) for x in cells[0].split('/') if x.strip()]
                        n += 1; gname = str(n)
                        pairs[gname] = [f"{st['first_name']} {st['last_name']}" for st in sts if st]
                        continue
                    else:
                        names = [x for x in cells if x]
                        n += 1; gname = str(n)
                    members = [full_name(x) for x in names]
                    members = [m for m in members if m]
                    if members:
                        pairs[gname] = members
                group_sets.append(dict(name=name, kind='partners', group_label=glabel, groups=[dict(name=k, members=v) for k, v in pairs.items()]))
                continue
            hdr_row = r0 + 1
            hdrs = {grid.get((hdr_row, cc), '').lower(): cc for cc in range(c0, c0 + 4)}
            c_first = next((cc for h, cc in hdrs.items() if h == 'first'), None)
            c_last = next((cc for h, cc in hdrs.items() if h == 'last'), None)
            c_group = next((cc for h, cc in hdrs.items() if h in ('team', 'group', 'age group')), None)
            if c_first is None:
                warnings.append(f'Groups Teams: no First/Last header under "{name}"; skipped'); continue
            groups, cur = {}, None if c_group is not None else ('Girls' if 'Girls' in name else 'All')
            for rr in range(hdr_row + 1, end + 1):
                g = grid.get((rr, c_group), '') if c_group is not None else ''
                if g:
                    cur = g
                first = grid.get((rr, c_first), '')
                last = grid.get((rr, c_last), '') if c_last else ''
                if not first or cur is None:
                    continue
                m = full_name(f'{first} {last}'.strip())
                if m and m not in groups.setdefault(cur, []):
                    groups[cur].append(m)
            group_sets.append(dict(name=name, kind='group', group_label=glabel, groups=[dict(name=k, members=v) for k, v in groups.items()]))

    for name, groups in group_list_sets:
        if not any(g['name'] == name for g in group_sets):
            group_sets.append(dict(name=name, kind='group', group_label='Team' if 'Presentation' in name else 'Group',
                                   groups=[dict(name=k, members=v) for k, v in groups.items()]))

    # ------------------------------------------------------------------ output
    out_students = []
    for k in order:
        st = students[k]
        st['notes'] = '\n'.join(dict.fromkeys(st['notes']))
        for drop in ('mom_email', 'dad_email', '_tuition_amount', '_tuition_text', '_at_home', '_hh_text'):
            st.pop(drop, None)
        out_students.append(st)
    data = dict(label=label, students=out_students, host_homes=list(homes.values()), mentors=list(mentors.values()),
                group_sets=group_sets, volunteers=list(volunteers.values()))
    with open(out, 'w') as fh:
        json.dump(data, fh, indent=2)
    print(f'Wrote {out} for {label}: {len(out_students)} students, {len(homes)} host homes, {len(mentors)} mentors, '
          f'{len(group_sets)} group sets, {len(volunteers)} volunteers')
    for w in dict.fromkeys(warnings):
        print('WARNING:', w)


if __name__ == '__main__':
    if len(sys.argv) not in (3, 4):
        print(__doc__)
        sys.exit(1)
    main(sys.argv[1], sys.argv[2], sys.argv[3] if len(sys.argv) == 4 else None)
