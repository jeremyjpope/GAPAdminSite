#!/usr/bin/env python3
"""
Convert the yearly "GAP Database" Excel workbook into JSON for `php spark gap:import`.

Usage:
    python3 tools/excel_to_json.py "/path/to/2026-27 Year 8 Database.xlsx" writable/import/year8.json

Requires: pip install openpyxl
The output contains personal data; writable/import/ is git-ignored. Review the printed warnings.
"""
import json
import re
import sys
from datetime import date, datetime

import openpyxl

MONTHS = ['July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March', 'April']


def s(v):
    if v is None:
        return ''
    if isinstance(v, (datetime, date)):
        return v.strftime('%Y-%m-%d')
    return str(v).strip()


def key(first, last):
    """Loose identity key: last name + first two letters of first name (handles Isa/Isabella, Josh/Joshua...)."""
    first = re.sub(r'[^A-Za-z]', '', first.split()[0] if first.strip() else '')
    return (re.sub(r'[^a-z]', '', last.lower()), first[:2].lower())


def split_names(raw):
    """'Joshua (Josh)' / 'Isa(Isabella)' -> (legal, preferred): the longer one is legal."""
    m = re.match(r'^\s*([^()]+?)\s*\(([^)]+)\)\s*$', raw)
    if not m:
        return raw.strip(), ''
    a, b = m.group(1).strip(), m.group(2).strip()
    return (a, b) if len(a) >= len(b) else (b, a)


def parse_date(txt, default_year=None):
    m = re.search(r'(\d{1,2})/(\d{1,2})/(\d{2,4})', txt)
    if m:
        mo, d, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
        y = y + 2000 if y < 100 else y
        try:
            return date(y, mo, d).isoformat()
        except ValueError:
            return None
    m = re.search(r'(\d{1,2})/(\d{1,2})', txt)
    if m and default_year:
        try:
            return date(default_year, int(m.group(1)), int(m.group(2))).isoformat()
        except ValueError:
            return None
    return None


def main(xlsx, out):
    wb = openpyxl.load_workbook(xlsx, data_only=True)
    warnings = []
    students = {}   # key -> dict
    order = []

    # 1. Student roster (preferred first names + emails) defines who is a student.
    for r in wb['Student Names & Email Addresses'].iter_rows(values_only=True):
        first, last, email = s(r[0]), s(r[1]), s(r[2])
        if not first or not last:
            continue
        k = key(first, last)
        students[k] = dict(first_name=first, last_name=last, preferred_name='', email=email.lower(), phone='', gender='',
                           birthday=None, address='', tshirt_size='', placement_preference='', allergies_medical='',
                           cleared_to_drive='', auto_insurance='', housing_preference='', notes=[], contacts=[], vehicles=[],
                           host_home=None, mentor=None, tuition=None, mom_email='', dad_email='')
        order.append(k)

    def find(name):
        parts = name.replace('(', ' (').split()
        parts = [p for p in parts if p]
        if len(parts) == 1:
            parts = parts + ['']
        if len(parts) < 2:
            return None
        # drop parenthesised nickname tokens
        clean = [p for p in parts if not p.startswith('(')]
        first, last = clean[0], clean[-1]
        k = key(first, last)
        if k in students:
            return students[k]
        # try any token as first name
        for p in clean:
            k = key(p, last)
            if k in students:
                return students[k]
        # last resort: a unique first-name match (handles a misspelt or missing last name)
        hits = [st for st in students.values() if first.lower() in (st['first_name'].lower(), st['preferred_name'].lower())]
        if len(hits) == 1:
            warnings.append(f'Matched "{name}" to {hits[0]["first_name"]} {hits[0]["last_name"]} by first name only')
            return hits[0]
        warnings.append(f'Could not match "{name}" to a student')
        return None

    # 2. Applicants: details for accepted students.
    ws = wb['Applicants']
    hdr = {s(c.value): i for i, c in enumerate(ws[1])}
    gender = ''
    for r in ws.iter_rows(min_row=2, values_only=True):
        rawfirst, last = s(r[hdr['First Name']]), s(r[hdr['Last Name']])
        if 'GUY' in rawfirst.upper() and not last:
            gender = 'M'
            continue
        if 'GIRL' in rawfirst.upper() and not last:
            gender = 'F'
            continue
        if not rawfirst or not last:
            continue
        legal, pref = split_names(rawfirst)
        k = key(legal, last)
        if k not in students:
            k2 = key(pref, last) if pref else None
            if k2 in students:
                k = k2
            else:
                continue  # not accepted
        st = students[k]
        if legal and legal != st['first_name']:
            st['preferred_name'] = st['first_name'] if not pref else pref
            st['first_name'] = legal
        elif pref:
            st['preferred_name'] = pref
        st['gender'] = gender
        st['email'] = st['email'] or s(r[hdr['Email Address']]).lower()
        st['phone'] = s(r[hdr['Phone Number']])
        st['address'] = s(r[hdr['Address']])
        st['birthday'] = s(r[hdr['Birthday']])[:10] or None
        st['tshirt_size'] = s(r[hdr['T-Shirt Size']])
        st['placement_preference'] = s(r[hdr['Preschool or Elementary Preference']])
        st['allergies_medical'] = s(r[hdr['Allergies?/Medical notes']])
        st['cleared_to_drive'] = s(r[hdr['Cleared to Drive']])
        st['auto_insurance'] = s(r[hdr['Auto Insurance']])
        st['housing_preference'] = s(r[hdr['Housing Preference']])
        st['mom_email'] = s(r[hdr["Mom's Email"]]).lower()
        st['dad_email'] = s(r[hdr["Dad's Email"]]).lower()
        tuition = s(r[hdr['Yearly Tuition']])
        st['_tuition_amount'] = float(tuition) if re.fullmatch(r'\d+(\.\d+)?', tuition) else None
        cl = s(r[hdr['Commitment Letter Signed']])
        if cl:
            st['notes'].append('Commitment letter: ' + cl)
        for col in ('Step 1 : Application',):
            if col in hdr and s(r[hdr[col]]):
                st['notes'].append('Application submitted: ' + s(r[hdr[col]])[:10])

    # 3. Vehicles
    for r in wb['Student Auto Information'].iter_rows(min_row=2, values_only=True):
        if not s(r[0]):
            continue
        st = find(f'{s(r[0])} {s(r[1])}')
        if st:
            st['vehicles'].append(dict(description=s(r[2]), vin=s(r[3]), oil_type=s(r[4]), oil_notes=s(r[5])))

    # 4. Mentors
    mentors = {}
    gender = ''
    for r in wb['Mentor Information'].iter_rows(values_only=True):
        a, b, c = s(r[0]), s(r[1]), s(r[2])
        if 'GUYS' in a.upper() and 'PREVIOUS' not in a.upper():
            gender = 'M'
            continue
        if 'GIRLS' in a.upper():
            gender = 'F'
            continue
        if 'PREVIOUS' in a.upper():
            break
        if a == 'Student' or not a or not b:
            continue
        email = c if '@' in c else ''
        if c and not email:
            warnings.append(f'Mentor email looks malformed for {b}: "{c}"')
        mentors.setdefault(b, dict(name=b, email=email, gender=gender))
        st = find(a)
        if st:
            st['mentor'] = b

    # 5. Host homes + parents
    homes = {}
    ws = wb['Host Home Assignments']
    section = 'HOST'
    current = None
    last_home = None
    for r in ws.iter_rows(min_row=5, values_only=True):
        a, b, c, d, e, f, g, h, i, j, k, l = (s(x) for x in r[:12])
        up = a.upper()
        if up.startswith('LIVING AT HOME'):
            section = 'HOME'; current = None; last_home = None; continue
        if up.startswith('STUDENT ACQUIRED'):
            section = 'OWN'; current = None; last_home = None; continue
        if up.startswith('FRIDAY MEAL') or up.startswith('SUB HOST') or up.startswith('POTENTIAL'):
            break
        if a in ('Name', 'Student') or (not a and not e and not h and not j):
            if not a and not e and h and section == 'HOST' and not current:
                pass
            continue
        if a and '@' in b:
            current = find(a)
            if not current:
                continue
            current['phone'] = current['phone'] or c
            current['address'] = current['address'] or d.replace('\n', ', ')
            if l:
                current['mentor'] = current['mentor'] or l
            if section == 'HOME':
                current['host_home'] = None
                last_home = None
            elif h or (section == 'HOST' and last_home and j):
                if h:
                    hk = h
                    homes.setdefault(hk, dict(key=hk, family_name=h, address=i.replace('\n', ', '), email='', email2='', phone='', phone2='', notes='' if section == 'HOST' else 'Student-acquired home'))
                    last_home = hk
                home = homes[last_home]
                for em, ph in ((j, k),):
                    if em and em.lower() not in (home['email'].lower(), home['email2'].lower()):
                        if not home['email']: home['email'] = em
                        elif not home['email2']: home['email2'] = em
                    if ph and ph not in (home['phone'], home['phone2']):
                        if not home['phone']: home['phone'] = ph
                        elif not home['phone2']: home['phone2'] = ph
                current['host_home'] = last_home
            if e:
                current['contacts'].append(dict(name=e, email=f, phone=g))
        elif current:
            # continuation row: second parent, second host contact
            if e:
                current['contacts'].append(dict(name=e, email=f, phone=g))
            if current['host_home'] and (h or j or k):
                home = homes[current['host_home']]
                if h and section == 'OWN' and h not in home['family_name']:
                    home['family_name'] += ' & ' + h
                if j and j.lower() not in (home['email'].lower(), home['email2'].lower()):
                    if not home['email']: home['email'] = j
                    elif not home['email2']: home['email2'] = j
                if k and k not in (home['phone'], home['phone2']):
                    if not home['phone']: home['phone'] = k
                    elif not home['phone2']: home['phone2'] = k

    # relation from mom/dad emails
    for st in students.values():
        for ct in st['contacts']:
            em = ct['email'].lower()
            ct['relation'] = 'Mother' if em and em == st['mom_email'] else 'Father' if em and em == st['dad_email'] else 'Parent'
        for m in list(mentors.values()):
            pass
        if st['mentor'] and st['mentor'] not in mentors:
            mentors[st['mentor']] = dict(name=st['mentor'], email='', gender='')

    # 6. Tuition
    ws = wb['Tuition Info']
    for r in ws.iter_rows(min_row=3, values_only=True):
        name, plan, sent = s(r[0]), s(r[1]), s(r[2])
        if not name or name == 'Invoice_ID' or re.fullmatch(r'\d+', name):
            if name == 'Invoice_ID':
                break
            continue
        st = find(name)
        if not st:
            continue
        pl = plan.lower()
        plan_type = 'month' if 'month' in pl else 'semester' if 'semester' in pl else 'year' if 'year' in pl else 'other'
        total = st.get('_tuition_amount')
        m = re.search(r'\$(\d[\d,]*)', plan)
        amt = float(m.group(1).replace(',', '')) if m else None
        if total is None and amt:
            total = amt * 10 if plan_type == 'month' else amt * 2 if plan_type == 'semester' else amt
        payments = []
        if 'PAID' in plan.upper() and plan_type in ('year', 'semester'):
            paid_amt = amt or (total if plan_type == 'year' else (total / 2 if total else None))
            if paid_amt:
                payments.append(dict(paid_on=parse_date(sent) or '2026-07-15', amount=paid_amt, method='online', reference='',
                                     note=f'Imported: plan marked PAID ("{plan}"). Date is the invoice-sent date; adjust if needed.'))
        if 'PAID' in plan.upper() and plan_type == 'month':
            warnings.append(f'{name}: monthly plan marked PAID but no amounts in month grid; no payments imported')
        for idx, month in enumerate(MONTHS):
            cell = s(r[3 + idx])
            if not cell:
                continue
            m = re.search(r'\$\s?(\d[\d,]*(?:\.\d\d)?)', cell)
            if not m:
                warnings.append(f'{name}: {month} cell has no $ amount, skipped: "{cell}"')
                continue
            ck = re.search(r'ck\.?\s*#?\s*(\d+)', cell, re.I)
            yr = 2026 if idx < 6 else 2027
            d = parse_date(cell, yr) or date(yr, [7, 8, 9, 10, 11, 12, 1, 2, 3, 4][idx], 1).isoformat()
            payments.append(dict(paid_on=d, amount=float(m.group(1).replace(',', '')), method='check' if ck else 'other',
                                 reference=f'ck #{ck.group(1)}' if ck else '', note=f'{month}: {cell}'))
        st['tuition'] = dict(plan_type=plan_type, total_due=total, notes='; '.join(x for x in (plan, sent) if x), payments=payments)

    # 7. Groups Teams: five regions with different shapes (see sheet)
    group_sets = []
    if 'Groups Teams' in wb.sheetnames:
        ws = wb['Groups Teams']
        rows = {r: {c.column_letter: s(c.value) for c in row if s(c.value)} for r, row in enumerate(ws.iter_rows(), start=1)}

        def names_of(st_name):
            st = find(st_name)
            return f"{st['first_name']} {st['last_name']}" if st else None

        def region(name, kind, label, col_group, col_first, col_last, r0, r1, carry=True, single=None):
            groups, cur = {}, single
            for r in range(r0, r1 + 1):
                row = rows.get(r, {})
                g = row.get(col_group) if col_group else None
                if g and not single:
                    cur = g
                first, last = row.get(col_first, ''), row.get(col_last, '')
                if not first:
                    continue
                if cur is None:
                    continue
                n = names_of(f'{first} {last}'.strip())
                if n:
                    groups.setdefault(cur, []).append(n)
            group_sets.append(dict(name=name, kind=kind, group_label=label, groups=[dict(name=k, members=v) for k, v in groups.items()]))

        region('Team Presentation Groups', 'group', 'Team', 'A', 'B', 'C', 3, 21)
        region("Fall Children's Learning Center", 'group', 'Age group', 'E', 'G', 'H', 3, 21)
        region("Guys' Life Skills & Friday Night Group", 'group', 'Group', 'A', 'C', 'D', 25, 40)
        region("Girls' Friday Night Group", 'group', 'Group', None, 'E', 'F', 25, 40, single='Girls')
        pairs = {}
        for r in range(24, 40):
            row = rows.get(r, {})
            if row.get('H') and (row.get('I') or row.get('J')):
                mem = [names_of(row[c]) for c in ('I', 'J', 'K', 'L') if row.get(c)]
                pairs[row['H']] = [m for m in mem if m]
        group_sets.append(dict(name='Accountability Partners', kind='partners', group_label='Pair', groups=[dict(name=k, members=v) for k, v in pairs.items()]))

    out_students = []
    for k in order:
        st = students[k]
        st['notes'] = '\n'.join(st['notes'])
        for drop in ('mom_email', 'dad_email', '_tuition_amount'):
            st.pop(drop, None)
        out_students.append(st)

    data = dict(students=out_students, host_homes=list(homes.values()), mentors=list(mentors.values()), group_sets=group_sets)
    with open(out, 'w') as fh:
        json.dump(data, fh, indent=2)
    print(f'Wrote {out}: {len(out_students)} students, {len(homes)} host homes, {len(mentors)} mentors, {len(group_sets)} group sets')
    for w in warnings:
        print('WARNING:', w)


if __name__ == '__main__':
    if len(sys.argv) != 3:
        print(__doc__)
        sys.exit(1)
    main(sys.argv[1], sys.argv[2])
