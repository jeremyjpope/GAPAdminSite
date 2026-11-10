#!/usr/bin/env python3
"""
Applicant tab(s) of a yearly GAP database workbook -> JSON for `php spark gap:import-applicants`.

    python3 tools/applicants_to_json.py "<year workbook.xlsx>" <out.json> <year label>

Format and rules: docs/applicants_import.md. The parser is header driven: every column is found by
its header text (row 1) through the ALIASES table, so the 2023-24 .. 2026-27 layouts all work.
Tabs: a single 'Applicants' tab with GUY/GIRL section rows, or separate Guys/Girls tabs (any tab
whose name contains GUY or GIRL, except 'Copy of ...' and budget tabs).
"""
import json
import re
import sys
from datetime import date, datetime

import openpyxl

GREEN = {'B6D7A8', 'D9EAD3', '93C47D', 'B7E1CD', 'C6EFCE'}
RED = {'F4CCCC', 'EA9999', 'FF0000', 'FFC7CE'}

# field / step -> header aliases (matched exact-then-prefix on a normalised form)
ALIASES = {
    'order':      ['Submission Order', 'Process Status'],
    'first':      ['First Name'],
    'last':       ['Last Name'],
    'email':      ['Email Address', 'Email'],
    'phone':      ['Phone Number', 'Phone'],
    'address':    ['Address', 'Hometown Address', 'Home Address', 'HomeTown'],
    'birthday':   ['Birthday'],
    'mom':        ["Mom's Email"],
    'dad':        ["Dad's Email"],
    'tshirt':     ['T-Shirt Size'],
    'placement':  ['Preschool or Elementary Preference', 'Church Serving Preference'],
    'medical':    ['Allergies?/Medical notes', 'Allergies?'],
    'drive':      ['Cleared to Drive'],
    'insurance':  ['Auto Insurance'],
    'vehicle':    ['Vehicle Information'],
    'housing':    ['Housing Preference'],
    'tuition':    ['Yearly Tuition', 'Tuition Payment', 'Spring Tuition Paid'],
    'notes':      ['Notes', 'NOTES'],
    # steps
    'applied':       ['Step 1 : Application'],
    'preview':       ['Invited to Preview? Attended?', 'Preview Week Communicated'],
    'video':         ['Video Received?'],
    'newsletter':    ['GAP Newsletter'],
    'personal_ref':  ['Personal Reference', 'Reference Reports received and reviewed'],
    'spiritual_ref': ['Spiritual Reference'],
    'interview':     ['Step 2:  Interview', 'Step 2: Interview', 'Step 3: Personal Interview Completed'],
    'decision':      ['Interview Result/Acceptance Notification Sent', 'Acceptance Notification sent'],
    'commitment':    ['Commitment Letter Signed', 'Commitment Letter received'],
    'background':    ['Response marked as', 'SAAT request made', 'Driver Opportunity Completed'],
    'books':         ['Books ordered and sent'],
    'canvas':        ['Added to Canvas Course'],
    'dates_doc':     ['Important Dates and Information Document Sent'],
}
STEPS = ['applied', 'preview', 'video', 'newsletter', 'personal_ref', 'spiritual_ref', 'interview',
         'decision', 'commitment', 'background', 'books', 'canvas', 'dates_doc']
MONTHS = {m: i for i, m in enumerate(['january', 'february', 'march', 'april', 'may', 'june', 'july',
                                      'august', 'september', 'october', 'november', 'december'], 1)}

warnings = []


def warn(msg):
    warnings.append(msg)
    print('WARNING:', msg, file=sys.stderr)


def s(v):
    if v is None:
        return ''
    if isinstance(v, (datetime, date)):
        return v.strftime('%Y-%m-%d')
    return re.sub(r'\s+', ' ', str(v)).strip()


def norm(h):
    return re.sub(r'[^a-z0-9]', '', str(h or '').lower())


def header_map(ws):
    return {norm(c.value): i for i, c in enumerate(ws[1]) if c.value}


def cols(hm, names):
    """All column indexes whose header matches one of the aliases (exact, then prefix)."""
    out = []
    for n in names:
        k = norm(n)
        if k in hm and hm[k] not in out:
            out.append(hm[k])
    for n in names:
        k = norm(n)
        for h, i in hm.items():
            if h.startswith(k) and i not in out:
                out.append(i)
    return out


def fill(c):
    try:
        if c.fill is not None and c.fill.fill_type == 'solid':
            rgb = c.fill.fgColor.rgb
            if isinstance(rgb, str) and rgb != '00000000':
                return rgb[-6:].upper()
    except Exception:
        pass
    return None


def legal_and_preferred(raw):
    """'Joshua (Josh)' / 'Isa(Isabella)' / 'William/Liam' -> (legal, preferred): the longer is legal."""
    raw = raw.strip()
    m = re.match(r'^\s*([^()/"]+?)\s*[(/"]\s*([^)/"]+?)\s*[)"]?\s*$', raw)
    if not m:
        return raw, ''
    a, b = m.group(1).strip(), m.group(2).strip()
    return (a, b) if len(a) >= len(b) else (b, a)


def season_year(month, label):
    """Application season for label 2026-27 runs Sep 2025 .. Aug 2026."""
    y1 = int(label[:4])
    return y1 - 1 if month >= 9 else y1


def find_date(txt, label):
    """First date in free text: m/d/yy, m.d.yy, yyyy-mm-dd or 'February 2nd' (year inferred from the season)."""
    if not txt:
        return None
    m = re.search(r'(\d{4})-(\d{2})-(\d{2})', txt)
    if m:
        try:
            return date(int(m.group(1)), int(m.group(2)), int(m.group(3))).isoformat()
        except ValueError:
            pass
    m = re.search(r'(?<![\d/])(\d{1,2})[/.](\d{1,2})[/.](\d{2,4})(?![\d/])', txt)
    if m:
        mo, d, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
        y = y + 2000 if y < 100 else y
        try:
            return date(y, mo, d).isoformat()
        except ValueError:
            pass
    m = re.search(r'\b(' + '|'.join(MONTHS) + r')\s+(\d{1,2})(?:st|nd|rd|th)?\b', txt, re.I)
    if m:
        mo, d = MONTHS[m.group(1).lower()], int(m.group(2))
        try:
            return date(season_year(mo, label), mo, d).isoformat()
        except ValueError:
            pass
    m = re.search(r'(?<![\d/])(\d{1,2})/(\d{1,2})(?![\d/])', txt)
    if m:
        mo, d = int(m.group(1)), int(m.group(2))
        try:
            return date(season_year(mo, label), mo, d).isoformat()
        except ValueError:
            pass
    return None


def is_date_cell(c):
    return isinstance(c.value, (datetime, date))


def starts_with_date(txt):
    return bool(re.match(r'^\s*(\d{4}-\d{2}-\d{2}|\d{1,2}[/.]\d{1,2}[/.]\d{2,4})', txt or ''))


def applicant_tabs(wb):
    out = []
    for ws in wb.worksheets:
        t = ws.title.strip()
        tl = t.lower()
        if tl.startswith('copy of') or 'budget' in tl:
            continue
        if tl == 'applicants':
            out.append((ws, ''))
        elif re.search(r'\bguys?\b', tl):
            out.append((ws, 'M'))
        elif re.search(r'\bgirls?\b', tl):
            out.append((ws, 'F'))
    return out


def parse_tab(ws, fixed_gender, label, headers_out):
    hm = header_map(ws)
    c = {k: cols(hm, v) for k, v in ALIASES.items()}
    if not c['first'] or not c['last']:
        warn(f"tab '{ws.title}': no First/Last Name headers, skipped")
        return []
    c_first, c_last = c['first'][0], c['last'][0]
    headers = {i: s(cell.value) for i, cell in enumerate(ws[1]) if cell.value}
    headers_out.update(headers)
    gender = fixed_gender
    rows = []
    for row in ws.iter_rows(min_row=2):
        first_raw = s(row[c_first].value) if c_first < len(row) else ''
        last_raw = s(row[c_last].value) if c_last < len(row) else ''
        if first_raw.lower() == 'first' and last_raw.lower() == 'last':
            break                                   # a second table (summary list) follows
        if '@' in last_raw:
            break                                   # email list appended below the table
        if first_raw and not last_raw:
            up = first_raw.upper()
            if 'GUY' in up:
                gender = 'M'
            elif 'GIRL' in up:
                gender = 'F'
            continue
        if not first_raw or not last_raw:
            continue
        rows.append((row, first_raw, last_raw, gender))

    def text_at(row, idxs):
        parts = []
        for i in idxs:
            if i < len(row):
                v = s(row[i].value)
                if v and v != '#VALUE!':
                    parts.append(v)
        return '; '.join(parts)

    out = []
    for row, first_raw, last_raw, gender in rows:
        legal, pref = legal_and_preferred(first_raw)
        raw = {}
        filled = 0
        for i, cell in enumerate(row):
            v = s(cell.value)
            if v and v != '#VALUE!' and i in headers:
                raw[headers[i]] = v
                filled += 1
        # column A: the submission order when numeric, otherwise a status hint (Withdrew, Stop Responding)
        order_txt = s(row[0].value) if c_first != 0 else text_at(row, c['order'])
        order = int(re.sub(r'\D', '', order_txt)) if re.search(r'\d', order_txt) and not re.search(r'[a-z]', order_txt, re.I) else None
        app = {
            'submission_order': order,
            'first_name': legal, 'preferred_name': pref or None, 'last_name': last_raw.rstrip('`'),
            'gender': gender or None,
            'email': text_at(row, c['email'][:1]).lower() or None,
            'phone': text_at(row, c['phone'][:1]) or None,
            'address': text_at(row, c['address'][:1]).replace(' / ', ', ') or None,
            'birthday': None,
            'mom_email': text_at(row, c['mom'][:1]) or None,
            'dad_email': text_at(row, c['dad'][:1]) or None,
            'tshirt_size': text_at(row, c['tshirt'][:1]) or None,
            'placement_preference': text_at(row, c['placement'][:1]) or None,
            'allergies_medical': text_at(row, c['medical'][:1]) or None,
            'cleared_to_drive': text_at(row, c['drive'][:1]) or None,
            'auto_insurance': text_at(row, c['insurance'][:1] + c['vehicle'][:1]) or None,
            'housing_preference': text_at(row, c['housing'][:1]) or None,
            'tuition_note': text_at(row, c['tuition'][:1]) or None,
            'notes': text_at(row, c['notes'][:1]) or None,
            'status': None,
            'steps': {},
            'raw': raw,
        }
        for i in c['birthday'][:1]:
            if i < len(row):
                cell = row[i]
                app['birthday'] = s(cell.value)[:10] if is_date_cell(cell) else (find_date(s(cell.value), label) if s(cell.value) else None)

        # steps
        for key in STEPS:
            idxs = [i for i in c[key] if i < len(row)]
            if not idxs:
                continue
            notes, done_on, done = [], None, False
            for i in idxs:
                cell = row[i]
                v = s(cell.value)
                if not v or v == '#VALUE!':
                    continue
                if is_date_cell(cell):
                    done_on = done_on or v[:10]
                    done = True
                else:
                    notes.append(v)
                    d = find_date(v, label)
                    if d and not done_on and (key in ('interview', 'decision', 'commitment', 'applied') or starts_with_date(v)):
                        done_on = d
                    if re.match(r'^(yes|done|complete|cleared|received|placed)\b', v, re.I) or starts_with_date(v):
                        done = True
                if fill(cell) in GREEN:
                    done = True
            if notes or done_on:
                app['steps'][key] = {'done_on': done_on, 'note': '; '.join(notes) or None, 'done': done}

        # status
        row_text = ' '.join(raw.values()).lower()
        name_fill = fill(row[c_first])
        decision = (app['steps'].get('decision') or {}).get('note') or ''
        commitment = (app['steps'].get('commitment') or {}).get('note') or ''
        dc = (decision + ' ' + commitment).lower()
        col_a = order_txt.lower()
        tuition_cells = [row[i] for i in c['tuition'][:1] if i < len(row)]
        tuition_paid = any(fill(tc) in GREEN or re.search(r'\bpaid\b', s(tc.value), re.I) for tc in tuition_cells)
        status = None
        if 'waitlist' in row_text:
            status = 'waitlisted'
        elif re.search(r'withdr|stop responding', col_a):
            status = 'withdrawn'
        elif re.search(r'not accepted|declin|not placed', dc) or 'declin' in col_a:
            status = 'declined'
        elif name_fill in RED:
            status = 'withdrawn' if re.search(r'withdr|stop responding|no response', row_text) else 'declined'
        elif name_fill in GREEN or 'accept' in col_a:
            status = 'accepted'
        elif re.search(r'accept(ance|ed)?\s+(email|notification|letter)s?\s+sent|acceptance sent|accepted via', dc):
            status = 'accepted'
        elif (app['steps'].get('decision') or {}).get('done_on') and starts_with_date(decision) or is_decision_date(row, c['decision']):
            status = 'accepted'
        elif (app['steps'].get('commitment') or {}).get('done_on') and (starts_with_date(commitment) or is_decision_date(row, c['commitment'])):
            status = 'accepted'
        elif tuition_paid:
            status = 'accepted'
        elif 'withdr' in row_text or 'stop responding' in row_text:
            status = 'withdrawn'
        else:
            status = 'withdrawn'
            warn(f"{label} {legal} {last_raw}: no acceptance/decline found, marked withdrawn")
        app['status'] = status
        app['_filled'] = filled
        out.append(app)

    # duplicate names in one tab: keep the fuller row
    best = {}
    for a in out:
        k = (a['first_name'].lower(), a['last_name'].lower())
        if k in best:
            warn(f"{label} {a['first_name']} {a['last_name']}: duplicate row in '{ws.title}', kept the fuller one")
            if a['_filled'] <= best[k]['_filled']:
                continue
        best[k] = a
    result = [a for a in out if best[(a['first_name'].lower(), a['last_name'].lower())] is a]
    for a in result:
        del a['_filled']
    return result


def is_decision_date(row, idxs):
    return any(i < len(row) and is_date_cell(row[i]) for i in idxs)


RUBRIC_CATS = {'hunger': 'hunger', 'humility': 'humility', 'honor': 'honor', 'fit': 'fit'}


def parse_rubric(wb):
    """The 'Interview Rubric' tab: a header row with merged category titles (Hunger, Humility, Honor, Fit),
    the next row with one scorer first name per column, then one applicant per row (first, last, scores).
    The guys' and girls' sections each repeat the headers. Empty cells are skipped (never scored as 0)."""
    ws = wb['Interview Rubric']
    rows = list(ws.iter_rows(values_only=True))
    out, cat_cols, scorers = [], {}, {}
    i = 0
    while i < len(rows):
        r = rows[i]
        vals = [s(v).lower() for v in r]
        if any(v in RUBRIC_CATS for v in vals):
            # category header: each category starts at its cell and runs until the next category / Total
            cat_cols, cur = {}, None
            for ci, v in enumerate(vals):
                if v in RUBRIC_CATS:
                    cur = RUBRIC_CATS[v]
                elif v == 'total':
                    cur = None
                if cur:
                    cat_cols[ci] = cur
            # scorer names: the next row that has text in a category column (may be the same row as 'GIRL APPLICANTS')
            j = i + 1
            while j < len(rows) and not any(s(rows[j][ci]) for ci in cat_cols):
                j += 1
            scorers = {ci: s(rows[j][ci]) for ci in cat_cols if s(rows[j][ci])}
            i = j + 1
            continue
        first, last = s(r[1]) if len(r) > 1 else '', s(r[2]) if len(r) > 2 else ''
        if first and last and cat_cols and not first.upper().endswith('APPLICANTS'):
            legal, pref = legal_and_preferred(first)
            scores = {}
            for ci, cat in cat_cols.items():
                v = r[ci] if ci < len(r) else None
                if isinstance(v, (int, float)) and ci in scorers:
                    scores.setdefault(cat, {})[scorers[ci]] = int(v)
            if scores:
                out.append({'first_name': legal, 'preferred_name': pref or None, 'last_name': last, 'scores': scores})
        i += 1
    return out


def main():
    if len(sys.argv) != 4:
        print(__doc__)
        sys.exit(1)
    path, out_path, label = sys.argv[1:4]
    if not re.match(r'^\d{4}-\d{2}$', label):
        sys.exit('label must look like 2026-27')
    wb = openpyxl.load_workbook(path, data_only=True)
    tabs = applicant_tabs(wb)
    if not tabs:
        sys.exit('no applicant tabs found')
    headers = {}
    applicants = []
    for ws, g in tabs:
        applicants += parse_tab(ws, g, label, headers)
    rubric = parse_rubric(wb) if 'Interview Rubric' in wb.sheetnames else []
    with open(out_path, 'w') as f:
        json.dump({'label': label, 'applicants': applicants, 'rubric': rubric, 'warnings': warnings}, f, indent=1)
    if rubric:
        print(f'rubric: {len(rubric)} applicants scored', file=sys.stderr)
    by_g, by_s, steps = {}, {}, {}
    for a in applicants:
        by_g[a['gender']] = by_g.get(a['gender'], 0) + 1
        by_s[a['status']] = by_s.get(a['status'], 0) + 1
        for k in a['steps']:
            steps[k] = steps.get(k, 0) + 1
    print(f"{label}: {len(applicants)} applicants from {[ws.title for ws, _ in tabs]}; gender {by_g}; status {by_s}")
    print('  steps:', dict(sorted(steps.items())))
    print(f'  {len(warnings)} warning(s)')


if __name__ == '__main__':
    main()
