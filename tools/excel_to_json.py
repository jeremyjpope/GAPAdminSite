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
    if toks and (toks[-1] in TITLES or (toks[-1].endswith('.') and len(toks) > 1)):
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

    # 1. Roster: a roster sheet if present, else everyone named in Host Home Assignments + Mentor Information.
    if 'Student Names & Email Addresses' in wb.sheetnames:
        for r in wb['Student Names & Email Addresses'].iter_rows(values_only=True):
            first, last, email = s(r[0]), s(r[1]), s(r[2])
            if first and last:
                new_student(first, last, email)
    else:
        if 'Host Home Assignments' in wb.sheetnames:
            for r in wb['Host Home Assignments'].iter_rows(values_only=True):
                a, b = s(r[0]), s(r[1])
                if a and '@' in b and a not in ('Name', 'Student'):
                    st = find(a, create=True, quiet=True)
                    if st and not st['email']:
                        st['email'] = b.lower()
        if 'Mentor Information' in wb.sheetnames:
            for r in wb['Mentor Information'].iter_rows(values_only=True):
                a, b = s(r[0]), s(r[1])
                if a and b and a != 'Student' and not a.isupper():
                    find(a, create=True, quiet=True)

    # 2. Applicant details ("Applicants" with GUY/GIRL sections, or "Guys" + "Girls" tabs)
    def apply_applicants(ws, fixed_gender=''):
        hm = header_map(ws)
        c_first, c_last = col(hm, 'First Name'), col(hm, 'Last Name')
        if c_first is None or c_last is None:
            return
        c = dict(email=col(hm, 'Email Address', 'Email'), phone=col(hm, 'Phone Number', 'Phone'),
                 address=col(hm, 'Address', 'Hometown Address'), tuition=col(hm, 'Yearly Tuition', 'Tuition Payment'),
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
            if tuition:
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

    if 'Applicants' in wb.sheetnames:
        apply_applicants(wb['Applicants'])
    for tab, g in (('Guys', 'M'), ('Girls', 'F')):
        if tab in wb.sheetnames:
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
    gender = ''
    if 'Mentor Information' in wb.sheetnames:
        for r in wb['Mentor Information'].iter_rows(values_only=True):
            a, b, c3 = s(r[0]), s(r[1]), s(r[2])
            up = a.upper()
            if 'PREVIOUS' in up:
                break
            if up == a and ('GUYS' in up or 'GIRLS' in up):
                gender = 'M' if 'GUYS' in up else 'F'
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

    # 5. Host homes + parents
    homes = {}
    if 'Host Home Assignments' in wb.sheetnames:
        ws = wb['Host Home Assignments']
        section, current, last_home = 'HOST', None, None
        for r in ws.iter_rows(min_row=5, values_only=True):
            a, b, c3, d, e, f, g, h, i, j, k, l = (s(x) for x in list(r[:12]) + [''] * (12 - len(r[:12])))
            up = a.upper()
            if up.startswith('LIVING AT HOME'):
                section, current, last_home = 'HOME', None, None; continue
            if up.startswith('STUDENT ACQUIRED'):
                section, current, last_home = 'OWN', None, None; continue
            if up.startswith(('FRIDAY MEAL', 'SUB HOST', 'POTENTIAL')):
                break
            if a in ('Name', 'Student') or (not a and not e and not h and not j):
                continue
            if h and (h.lower().startswith('requested') or h.lower() in ('host home (hh)', "host's names")):
                h = ''
            if a and '@' in b:
                current = find(a)
                if not current:
                    continue
                current['email'] = current['email'] or b.lower()
                current['phone'] = current['phone'] or c3
                current['address'] = current['address'] or d.replace('\n', ', ')
                if l:
                    current['mentor'] = current['mentor'] or l
                if section == 'HOME':
                    current['host_home'], last_home = None, None
                elif h or (section == 'HOST' and last_home and j):
                    if h:
                        clean = re.sub(r'^\s*renting\s+from\s+', '', h, flags=re.I).strip()
                        note = '' if section == 'HOST' else ('Student-acquired home (renting)' if clean != h else 'Student-acquired home')
                        homes.setdefault(clean, dict(key=clean, family_name=clean, address=i.replace('\n', ', ').split(', , ')[0], notes=note,
                                                    contacts=[]))
                        last_home = clean
                    home = homes[last_home]
                    home['contacts'].append(dict(email=j, phone=k))
                    current['host_home'] = last_home
                if e:
                    current['contacts'].append(dict(name=e, email=f, phone=g))
            elif current:
                if e:
                    current['contacts'].append(dict(name=e, email=f, phone=g))
                if current['host_home'] and (h or j or k):
                    home = homes[current['host_home']]
                    if h and section == 'OWN' and h not in home['family_name']:
                        home['family_name'] += ' & ' + h
                        home['key'] = home['family_name']
                        homes[home['key']] = homes.pop(current['host_home'])
                        for stx in students.values():
                            if stx['host_home'] == current['host_home']:
                                stx['host_home'] = home['key']
                        last_home = home['key']
                    home['contacts'].append(dict(email=j, phone=k))
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
    for st in students.values():
        txt = st.pop('_tuition_text', '')
        if st['tuition'] or not txt:
            continue
        plan_type = plan_from_text(txt)
        amounts = [float(a.replace(',', '')) for a in re.findall(r'\$\s?(\d[\d,]*(?:\.\d\d)?)', txt)]
        tier = None
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
        for seg in re.split(r';|,(?=[^,]*\$)|\band\b(?=[^;]*\$)', txt):
            m = re.search(r'\$\s?(\d[\d,]*(?:\.\d\d)?)', seg)
            if not m:
                continue
            low = seg.lower()
            if 'paid' not in low and 'deposit' not in low and 'check' not in low and 'ck' not in low and 'online' not in low and 'cash' not in low:
                continue
            method = 'online' if 'online' in low else 'check' if ('check' in low or re.search(r'\bck\b', low)) else 'cash' if 'cash' in low else 'other'
            ck = re.search(r'(?:check|ck)\s*#?\s*(\d+)', seg, re.I)
            d_ = parse_date(seg, year_for_month)
            payments.append(dict(paid_on=d_ or f'{start_year}-08-01', amount=float(m.group(1).replace(',', '')),
                                 method=method, reference=f'ck #{ck.group(1)}' if ck else '',
                                 note='Imported: ' + seg.strip()[:160] + ('' if d_ else ' (date not in sheet; defaulted to Aug 1)')))
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

    if 'Volunteer List' in wb.sheetnames:
        ws = wb['Volunteer List']
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
                add_role(v, role, cat if role in ('Life Skills', 'Fitness') else '')
            if cat and not any(x['role'] in ('Life Skills', 'Fitness') for x in v['roles']):
                add_note(v, 'Life skills category: ' + cat)
            if any(x['role'] == 'Potential Volunteer' for x in v['roles']):
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
    if 'Groups Teams' in wb.sheetnames:
        ws = wb['Groups Teams']
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

        def region_end(r0, c0):
            later = [rr for rr, cc, _, _ in titles if rr > r0 and abs(cc - c0) <= 2]
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

    # ------------------------------------------------------------------ output
    out_students = []
    for k in order:
        st = students[k]
        st['notes'] = '\n'.join(dict.fromkeys(st['notes']))
        for drop in ('mom_email', 'dad_email', '_tuition_amount', '_tuition_text'):
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
