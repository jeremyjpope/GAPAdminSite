#!/usr/bin/env python3
"""
Convert one "LS & ST Map" tab of the Life Skills & Special Topics Maps workbook into JSON for
`php spark gap:import-schedule`. The JSON contract is in docs/schedule_import.md.

    python3 tools/map_to_json.py "<workbook.xlsx>" "<sheet name>" <out.json> <year label>

The sheet is one row per calendar day. The header row (found by the cell that reads "Date")
names the time slots to the right of the date column; the GUYS/GIRLS comment columns end the
slot area. Column "Topic" holds merged block labels, column "GAP Week" merged week numbers.
Every cell under a slot header is free text; parse_cell() pulls out the course, LO/LA codes,
group, lead, location and confirmation marks as best it can and keeps the raw text.
"""
import datetime
import json
import re
import sys

import openpyxl
from openpyxl.utils import get_column_letter

WARNINGS = []


def warn(msg):
    WARNINGS.append(msg)
    print('WARNING: ' + msg, file=sys.stderr)


def ws_collapse(s):
    return re.sub(r'\s+', ' ', str(s)).strip()


# ----------------------------------------------------------------------------------------------
# Header: slots and comment columns
# ----------------------------------------------------------------------------------------------
TIME_RE = re.compile(r'(\d{1,2}(?::\d{2})?)\s*-\s*(\d{1,2}(?::\d{2})?|noon)\s*(am|pm)?', re.I)


def hm(token, pm):
    """'7:30' -> (7, 30); applies pm."""
    if token.lower() == 'noon':
        return 12, 0
    h, _, m = token.partition(':')
    h = int(h)
    m = int(m or 0)
    if pm and h < 12:
        h += 12
    return h, m


def parse_time_range(text):
    """Returns ('HH:MM', 'HH:MM') or (None, None)."""
    m = TIME_RE.search(text)
    if not m:
        return None, None
    a, b, suffix = m.group(1), m.group(2), (m.group(3) or '').lower()
    if b.lower() == 'noon':
        pm_a, pm_b = False, False
    elif suffix == 'pm':
        pm_b = True
        pm_a = int(a.split(':')[0]) <= int(b.split(':')[0])  # '1-3:30 pm' both pm; '10:30-1 pm' start am
    elif suffix == 'am':
        pm_a = pm_b = False
    else:
        pm_a = int(a.split(':')[0]) < 7
        pm_b = int(b.split(':')[0]) < 7
    ha, ma = hm(a, pm_a)
    hb, mb = hm(b, pm_b)
    return f'{ha:02d}:{ma:02d}', f'{hb:02d}:{mb:02d}'


def slot_audience(text):
    t = text.upper()
    if 'GUYS' in t or 'MEN' in t.split():
        return 'guys'
    if 'GIRLS' in t or 'WOMEN' in t.split():
        return 'girls'
    return 'coed'


def nice_slot_label(text):
    """'COED MORNING 7:30-9:00 am' -> 'Coed Morning 7:30-9:00'."""
    t = ws_collapse(text)
    t = re.sub(r'\s*(am|pm)\b', '', t, flags=re.I)
    words = []
    for w in t.split(' '):
        words.append(w.capitalize() if w.isalpha() else w)
    return ' '.join(words)


# ----------------------------------------------------------------------------------------------
# Dates
# ----------------------------------------------------------------------------------------------
def year_pair(label):
    m = re.match(r'^(\d{4})-(\d{2})$', label)
    if not m:
        sys.exit('Year label must look like 2026-27')
    y1 = int(m.group(1))
    return y1, y1 + 1


def parse_date(value, years):
    if isinstance(value, datetime.datetime):
        return value.date()
    if isinstance(value, datetime.date):
        return value
    if value is None:
        return None
    s = ws_collapse(value)
    for fmt in ('%A, %B %d', '%a %m/%d', '%m/%d', '%B %d'):
        try:
            d = datetime.datetime.strptime(s, fmt)
            y = years[0] if d.month >= 8 else years[1]
            return d.replace(year=y).date()
        except ValueError:
            pass
    for fmt in ('%Y-%m-%d', '%m/%d/%Y', '%m/%d/%y'):
        try:
            return datetime.datetime.strptime(s, fmt).date()
        except ValueError:
            pass
    return None


# ----------------------------------------------------------------------------------------------
# Cell text parsing
# ----------------------------------------------------------------------------------------------
LS = 'lifeskills'

# (regex on the collapsed text, course name, pillar, category). First match wins. The matched
# prefix is removed from the text before the title is derived.
COURSE_RULES = [
    (r'^(phys\.? ?ed|physical fitness)\b:?', 'Physical Fitness', LS, 'Phys Ed'),
    (r'^outdoors\b:?', 'Outdoors', LS, 'Outdoors'),
    (r'^home\b:?', 'Home', LS, 'Home'),
    (r'^artisanship\b:?', 'Artisanship', LS, 'Artisanship'),
    (r'^automotive\b:?', 'Automotive', LS, 'Automotive'),
    (r'^creation care\b:?', 'Creation Care', LS, 'Creation Care'),
    (r'^vocation(al)?( mission)?\b:?', 'Vocational Mission', LS, 'Vocation'),
    (r'^chapel\b', 'Chapel', 'bible', None),
    (r'^(calvin )?devotion(al)?\b', 'Devotional', 'bible', None),
    (r'^bible study methods\b', 'Bible Study Methods', 'bible', None),
    (r'^bible\b', 'Biblical Exposition', 'bible', None),
    (r'^(christian )?worldview\b', 'Christian Worldview', 'worldview', None),
    (r'^cultural engagement\b', 'Cultural Engagement', 'worldview', None),
    (r'^church history\b', 'Church History', 'worldview', None),
    (r'^(life|biblical) leadership\b', 'Biblical Leadership', 'leadership', None),
    (r'^(church service & )?ministry investment\b', 'Ministry Investment', 'formation', None),
    (r'^(accountability|discipleship) meetings\b', 'Ministry Investment', 'formation', None),
    (r'^sabbath\b', 'Sabbath', 'formation', None),
    (r'^open\b', 'Open', 'other', None),
    (r'^student presentations?\b', 'Student Presentations', 'other', None),
    (r'^communication\b', 'Communication', 'special', None),
    (r'^study hall\b', 'Study Hall', 'other', None),
]

# Events and breaks: (regex anywhere in the text, course name, pillar)
EVENT_RULES = [
    (r'labor day', 'Labor Day', 'break'),
    (r'thanksgiving', 'Thanksgiving Break', 'break'),
    (r'christmas break', 'Christmas Break', 'break'),
    (r'spring break', 'Spring Break', 'break'),
    (r'christmas\s*/\s*new year', 'Christmas Break', 'break'),
    (r'good friday', 'Good Friday', 'break'),
    (r'\beaster\b', 'Easter', 'break'),
    (r'mlk', 'MLK Holiday', 'break'),
    (r'\bholiday\b', None, 'break'),
    (r'\boff (day|week)\b', 'Off Day', 'break'),
    (r'students? move[- ]?in', 'Students Move In', 'event'),
    (r'commissioning', 'Commissioning Ceremony', 'event'),
    (r'presentation to dbc', 'Presentation to DBC', 'event'),
    (r'camping 101', 'Camping 101', 'event'),
    (r'camping', 'Camping', 'event'),
    (r'challenge (course|retreat)', 'Challenge Course Event', 'event'),
    (r'drew intensive', 'Drew Intensive', 'event'),
    (r'gunsight', 'Gunsight Ranch', 'event'),
    (r'cowtown', 'Cowtown Half Marathon', 'event'),
    (r'mission trip', 'Mission Trip', 'event'),
    (r'christmas party', 'Christmas Party', 'event'),
    (r'operation christmas child', 'Operation Christmas Child', 'event'),
    (r'volunteer appreciation', 'Volunteer Appreciation', 'event'),
    (r'golden whisk', 'Golden Whisk', 'event'),
    (r'childbirth class', 'Childbirth Class', 'event'),
    (r'broken bow', 'Broken Bow Retreat', 'event'),
    (r'\bretreat\b', 'Retreat', 'event'),
    (r'graduation', 'Graduation', 'event'),
]
# Generic event words, tried only when no course prefix matched (so "Hangar Conference" or a
# Creation Care farm tour stay with their course)
WEAK_EVENT_RULES = [
    (r'campout', None, 'event'),
    (r'field ?trip', None, 'event'),
    (r'\btour\b', None, 'event'),
    (r'conference', None, 'event'),
    (r'celebration', None, 'event'),
    (r'affirmation breakfast', 'Affirmation Breakfast', 'event'),
    (r'\bxite\b', 'Xite', 'event'),
]

# Course names that stand on their own without an "ST:" prefix
SPECIAL_RULES = [
    (r'^(special topic|st)\s*:', None),
    (r'^public speaking\b', 'Public Speaking'),
    (r'^interview skills?\b', 'Interview Skills'),
    (r'^parenting\b', 'Parenting'),
    (r'^networking\b', 'Networking'),
    (r'^elective', 'Elective'),
]

# A cell that only names a teacher: name -> (course, pillar)
TEACHER_COURSES = [
    (r'tommy nelson|tommy time', 'Biblical Exposition', 'bible', 'Tommy Nelson'),
    (r'zeinstra', 'Cultural Engagement', 'worldview', 'Bob Zeinstra'),
    (r'bruton', 'Relationships & Sexuality', 'leadership', 'Amy Bruton'),
]

# Keywords that identify a life-skills category when the cell has no category prefix
# (typical of the girls' life-skill cells): keyword -> (course, category)
KEYWORD_CATEGORY = [
    (r'sewing|baking|cooking|bread ?making|embroidery|grill|hospitality|cleaning|laundry|mending|ironing', 'Home', 'Home'),
    (r'woodworking|knives|knife|welding|leather|metalwork|blacksmith', 'Artisanship', 'Artisanship'),
    (r'first aid|cpr|gun|shooting|fishing|survival|hunting|backpacking', 'Outdoors', 'Outdoors'),
    (r'furniture|refinish', 'Artisanship', 'Artisanship'),
    (r'organization', 'Home', 'Home'),
    (r'garden|planting|landscap|compost|farm|rosegate|shiloh|dyno|recycl|heritage center', 'Creation Care', 'Creation Care'),
    (r'running|run training|jiu|defensive tactics|self[- ]defen', 'Physical Fitness', 'Phys Ed'),
]

MARK_RE = re.compile(r'\*{1,2}\s*(confirmed(?:\s+by)?(?:\s+[A-Z][\w]*)?|missed|[A-Z]{2})\s*\*{0,2}(?=\s|$)', re.I)
MARK2_RE = re.compile(r'[.\s]\b(confirmed(?:\s+by)?(?:\s+[A-Z]{2}|\s+[A-Z][a-z]+)?)\s*$', re.I)
LOLA_RE = re.compile(r'\(?\bLO\s?\d[\d,]*(?:\s?/\s?LA\s?\d+(?:\s?[,&/\-]\s?\d+)*)?\)?')
GROUP_RE = re.compile(r'\bgroups?\s+([A-D])\b(?:\s*[&,/]\s*([A-D])\b)?(?::?\s*\d{1,2}(?::\d{2})?\s*[ap]?\s*-\s*\d{1,2}(?::\d{2})?\s*[ap]\b)?:?', re.I)
GUYS_GROUP_RE = re.compile(r'\bguys\s+([AB])\b', re.I)
ALL_GIRLS_RE = re.compile(r'\ball girls\b', re.I)
LEAD_RE = re.compile(r'\b(?:leads?|instructor|teacher):\s*(.+?)(?=\s*(?:\(|location:|contact:|note:|@|\*|groups?\b|guys\b|girls\b|lo\d|\d{1,2}:\d{2}|$))', re.I)
# words that show a captured "name" is not a person
NOT_A_NAME = {'the', 'team', 'tbd', 'all', 'church', 'goals', 'map', 'life', 'college', 'impact', 'manager', 'operations',
              'city', 'development', 'child', 'etc', 'strategic', 'global', 'work', 'how', 'students', 'class', 'kitchen',
              'hangar', 'home', 'shop', 'classroom', 'group', 'and', 'or', 'ministry', 'lunch', 'tour', 'project'}
TEAM_RE = re.compile(r"((?:[A-Z][\w.'-]*\s){1,2}[A-Z][\w.'-]*)\s*&\s*Team:?", re.I)
WSLASH_RE = re.compile(r"\bw/?\s+(?=[A-Z])((?:(?:Judge|Dr\.?|Coach)\s+)?(?:[A-Z][\w.'-]*(?:\s|$)){1,3}(?:&\s*(?:[A-Z][\w.'-]*(?:\s|$)){1,2})?)")
WITH_RE = re.compile(r"\bwith\s+((?:(?:Judge|Dr\.?|Coach)\s+)?(?:[A-Z][\w.'-]*(?:\s|$)){1,3})")
BY_RE = re.compile(r"\bby\s+((?:[A-Z][\w.'-]*(?:\s|$)){2,3})")
LOCATION_RE = re.compile(r'\blocation:\s*(.+?)(?=\s*(?:\*|leads?:|instructor:|contact:|note:|groups?\b|\bconfirmed\b|$))', re.I)
AT_RE = re.compile(r'(?<![\w@])@\s*(?!\d|noon)(.+?)(?=\s*(?:\*|leads?:|instructor:|contact:|note:|groups?\b|location|\bconfirmed\b|$))', re.I)
HANGAR_RE = re.compile(r'\b(Hang[ae]r(?: (?:Classroom|Conference(?: room)?|Downstairs|Parking Lot|Classroom))?(?:[,&]\s*\w+(?: \w+)?)*)', re.I)
# a time range or single time that opens a session: "8-9 am", "9-10:30 am", "8:00-8:45", "9- noon", "8am", "10:30 am:"
TIME_SPLIT_RE = re.compile(
    r'(?<![\w/&,])((?:\d{1,2}(?::\d{2})?\s*-\s*(?:\d{1,2}(?::\d{2})?|noon)\s*(?:[ap]m\b)?|\d{1,2}(?::\d{2})?\s*[ap]m\b)(?=\s*:|\s+))', re.I)
PERSON_RE = r"[A-Z][a-z]+(?:\s+(?:[A-Z]\.|[A-Z][a-zA-Z'\-]+)){1,2}"
PERSON1_RE = r"[A-Z][a-z]+(?:\s+(?:[A-Z]\.|[A-Z][a-zA-Z'\-]+)){0,2}"


def clean_title(s):
    s = re.sub(r'\s+', ' ', s)
    s = re.sub(r'\s*([:;,.\-&]|/)\s*$', '', s)       # trailing punctuation
    s = re.sub(r'^\s*([:;,.\-&]|/)\s*', '', s)       # leading punctuation
    s = re.sub(r'\(\s*\)', '', s)
    s = re.sub(r'\s+', ' ', s).strip(' :;,-')
    return s


def clean_name(n):
    n = n.strip(" :;,.-&*?")
    n = re.sub(r"'s\s+team.*$", '', n, flags=re.I)
    n = re.sub(r"'s$", '', n)
    n = re.sub(r'^(Judge|Dr\.?|Coach)\s+', '', n)
    # keep only the leading run of capitalised words / initials ("Corey The global church" -> "Corey")
    words = []
    for w in n.split():
        if w.lower().strip('.') in NOT_A_NAME or not (w[0].isupper() or re.match(r"^[A-Z]\.?$", w)):
            break
        words.append(w)
    return ' '.join(words).strip()


def extract_staff(text):
    """Pull lead/instructor names out of the text. Returns (names, text_without_them, note)."""
    staff = []
    note = ''

    def take(m, grp=1):
        nonlocal text
        name = clean_name(m.group(grp))
        text = text[:m.start()] + ' ' + text[m.end():]
        return name

    for m in list(LEAD_RE.finditer(text))[::-1]:
        name = m.group(1)
        if '& Team' in name or '& team' in name:
            note = '(with team)'
            name = re.sub(r'\s*&\s*team', '', name, flags=re.I)
        text = text[:m.start()] + ' ' + text[m.end():]
        for part in re.split(r'\s*(?:,|&| and )\s*', name):
            part = clean_name(part)
            if part and len(part) < 40:
                staff.append(part)
    m = TEAM_RE.search(text)
    if m:
        staff.append(take(m))
        note = '(with team)'
    for rx in (WSLASH_RE, WITH_RE, BY_RE):
        for m in list(rx.finditer(text))[::-1]:
            for part in re.split(r'\s*&\s*', take(m)):
                staff.append(clean_name(part))
    return [s for s in staff if s and len(s) < 40], text, note


def extract_location(text):
    loc = None
    for rx in (LOCATION_RE, AT_RE):
        m = rx.search(text)
        if m:
            loc = clean_title(m.group(1))
            text = text[:m.start()] + ' ' + text[m.end():]
            break
    if loc is None:
        m = HANGAR_RE.search(text)
        if m:
            loc = clean_title(m.group(1))
            text = text[:m.start()] + ' ' + text[m.end():]
    return loc, text


def extract_group(text):
    letters = []
    for m in GROUP_RE.finditer(text):
        letters.append(m.group(1).upper())
        if m.group(2):
            letters.append(m.group(2).upper())
    text = GROUP_RE.sub(' ', text)
    for m in GUYS_GROUP_RE.finditer(text):
        letters.append(m.group(1).upper())
    text = GUYS_GROUP_RE.sub(' ', text)
    audience = None
    if ALL_GIRLS_RE.search(text):
        audience = 'girls'
        text = ALL_GIRLS_RE.sub(' ', text)
    seen = []
    for l in letters:
        if l not in seen:
            seen.append(l)
    return (' & '.join(seen) if seen else None), text, audience


def extract_marks(text):
    """Confirmation / missed marks -> (status, comment, text)."""
    status = 'planned'
    comments = []
    for m in list(MARK_RE.finditer(text))[::-1]:
        mark = m.group(1).strip()
        if mark.lower() == 'missed':
            status = 'missed'
        elif mark.lower().startswith('confirmed') or re.fullmatch(r'[A-Z]{2}', mark):
            status = 'confirmed'
        comments.append(mark if mark.lower() != mark else mark.capitalize())
        text = text[:m.start()] + ' ' + text[m.end():]
    m = MARK2_RE.search(text)
    if m:
        status = 'confirmed'
        comments.append(m.group(1))
        text = text[:m.start()]
    text = text.replace('*', ' ')
    return status, ', '.join(comments[::-1]), text


def extract_lola(text):
    codes = [clean_title(c.strip('()')) for c in LOLA_RE.findall(text)]
    text = LOLA_RE.sub(' ', text)
    text = re.sub(r'(^|\s)&(\s*&)+', r'\1&', text)
    text = re.sub(r'^\s*(&\s*)+', '', text)
    return ('; '.join(codes) if codes else None), text


def special_topic_name(rest):
    """Name of a Special Topic: text up to the first lead/location/person marker."""
    m = re.split(r'\s+(?:w/|with|lead:|leads:|location:|@|\*|\()', rest, maxsplit=1, flags=re.I)
    name = clean_title(m[0])
    tail = rest[len(m[0]):] if len(m) > 1 else ''
    # strip LO/LA and groups from the name itself
    name = clean_title(LOLA_RE.sub(' ', GROUP_RE.sub(' ', name)))
    name = name.rstrip('?').strip()
    if name.isupper():
        name = name.title()
    return name, tail


def base_entry(audience):
    return {
        'course': None, 'pillar': 'other', 'category': None, 'audience': audience, 'title': '',
        'group': None, 'lo_la': None, 'staff': [], 'location': None, 'status': 'planned', 'comment': '',
    }


def finish(entry, text, status, marks, staff_note=''):
    """Common tail of parsing: codes, group, lead, location, title."""
    lo_la, text = extract_lola(text)
    group, text, aud = extract_group(text)
    location, text = extract_location(text)
    names, text, note = extract_staff(text)
    title = clean_title(re.sub(r'\btopic:\s*', '', text, flags=re.I))
    if note or staff_note:
        title = clean_title(title + ' ' + (note or staff_note))
    entry['lo_la'] = lo_la
    entry['group'] = group
    if aud:
        entry['audience'] = aud
    entry['staff'] = [{'name': n, 'role': 'instructor'} for n in names]
    entry['location'] = location
    entry['title'] = title
    entry['status'] = status
    entry['comment'] = marks
    return entry


PARSE_CONTEXT = {}   # 'category': life-skills category of the current block, when the block is one


def parse_segment(text, audience, time_prefix=''):
    """One piece of cell text (after any time split) -> list of entry dicts."""
    out = _parse_segment(text, audience)
    if time_prefix:
        for e in out:
            if not e['title'].startswith(time_prefix):
                e['title'] = clean_title(time_prefix + ' ' + e['title'])
    return out


def _parse_segment(text, audience):
    t = ws_collapse(text)
    status, marks, t = extract_marks(t)
    t = ws_collapse(t)
    # "!!NOTE: ... AUTOMOTIVE: ..." -> the note goes to the comment
    m = re.match(r'^!*\s*note:?\s*(.*?)\s*(?=(?:outdoors|home|artisanship|automotive|creation care|vocation|st:|phys)\b)', t, re.I)
    if m and m.group(1):
        marks = clean_title(marks + '; ' + m.group(1).strip('! '))
        t = t[m.end():]
    # "GUYS ONLY ..." / "Girls: ..."
    m = re.match(r'^(guys|girls)(?: only)?\s*[:\-]?\s+', t, re.I)
    if m:
        audience = m.group(1).lower()
        t = t[m.end():]
    low = t.lower()
    out = []

    # Breaks and events (whole-cell matches win over everything)
    for rx, course, pillar in EVENT_RULES:
        if re.search(rx, low):
            e = base_entry(audience)
            e['course'], e['pillar'] = course, pillar
            rest = re.sub(rx, ' ', t, flags=re.I) if course else ''
            if course is None:
                loc, body = extract_location(t)
                names, body, _ = extract_staff(body)
                e['course'] = clean_title(body).rstrip('?') or clean_title(t)
                if e['course'].isupper():
                    e['course'] = e['course'].title()
                rest = ('w/ ' + ', '.join(names) if names else '') + (' @ ' + loc if loc else '')
            rest = re.sub(r'^\s*(101|event|break|@ ddr)\b', ' ', rest, flags=re.I)
            out.append(finish(e, rest, status, marks))
            return out

    # "Amy Bruton Biblical Sexuality" -> "Biblical Sexuality with Amy Bruton"
    m = re.match(r'^(amy bruton)\.?\s+(?=\S)', t, re.I)
    if m and re.search(r'sexuality|relationship|dating', low):
        t = t[m.end():] + ' with Amy Bruton'
        low = t.lower()

    # A cell that is only a teacher's name (or "ST: Bob Zeinstra")
    bare = re.sub(r'^(st|special topic)\s*:\s*', '', t, flags=re.I).strip('" ')
    for rx, course, pillar, person in TEACHER_COURSES:
        if re.search(rx, bare, re.I) and len(bare) <= 60 and not re.search(r'sexuality|relationship|dating', low):
            e = base_entry(audience)
            e['course'], e['pillar'] = course, pillar
            rest = re.sub(rx, ' ', bare, flags=re.I)
            rest = re.sub(r"\b(a|amy|bob|tommy|extended session with)\b\.?", ' ', rest, flags=re.I)
            e = finish(e, rest, status, marks)
            e['staff'] = [{'name': person, 'role': 'instructor'}]
            out.append(e)
            return out

    # "Group A: Rosegate Group B: Drew Groups C/D: Elise" -> one entry per group; the block's
    # life-skills category (passed in via audience_block) names the course
    if re.match(r'^groups?\s+[a-d]\b', low):
        parts = re.split(r'(?=\bgroups?\s+[A-D]\b)', t, flags=re.I)
        for part in parts:
            part = part.strip()
            if not part:
                continue
            g, rest, _ = extract_group(part)
            rest = re.sub(r'^\s*:\s*', '', rest)
            cat = PARSE_CONTEXT.get('category')
            for krx, kcourse, kcat in KEYWORD_CATEGORY:
                if re.search(krx, rest.lower()):
                    cat = kcat
                    break
            e = base_entry(audience)
            if cat:
                e['course'], e['pillar'], e['category'] = cat, LS, cat
            else:
                e['course'] = 'Life Skills'
                e['pillar'] = LS
                warn(f'Group-only cell with no category context: {text!r}')
            e = finish(e, rest, status, marks)
            e['group'] = g
            out.append(e)
        return out

    # Manhood / Womanhood, Relationships & Dating: coed cells that are really two classes
    if re.match(r'^manhood\s*/\s*womanhood', low) or re.match(r'^womanhood\s*/\s*manhood', low):
        rest = re.sub(r'^(manhood|womanhood)\s*/\s*(womanhood|manhood)\.?', '', t, flags=re.I)
        for course, aud in (('Manhood', 'guys'), ('Womanhood', 'girls')):
            e = base_entry(aud)
            e['course'], e['pillar'] = course, 'leadership'
            out.append(finish(e, rest, status, marks))
        return out
    if re.match(r'^(womanhood|manhood)\b', low):
        first = 'Womanhood' if low.startswith('womanhood') else 'Manhood'
        rest = re.sub(r'^(womanhood|manhood)\.?', '', t, flags=re.I)
        e = base_entry('girls' if first == 'Womanhood' else 'guys')
        e['course'], e['pillar'] = first, 'leadership'
        if first == 'Womanhood' and re.search(r'guys|nate|gordon|bruce', rest, re.I):
            g = base_entry('guys')
            g['course'], g['pillar'] = 'Purity & Addiction', 'leadership'
            out.append(finish(g, re.sub(r'\bguys\b-?', ' ', rest, flags=re.I), status, marks))
            rest = ''
        out.insert(0, finish(e, rest, status, marks))
        return out
    if re.match(r'^(relationships|bibl*ical sexuality|biblical relationships)', low):
        rest = re.sub(r'^(relationships\s*(&|and)\s*(dating|sexuality)|bibl*ical sexuality|biblical relationships)\.?', '', t, flags=re.I)
        girls = base_entry('girls')
        girls['course'], girls['pillar'] = 'Relationships & Sexuality', 'leadership'
        guys_part = re.search(r'(guys[\s\-–:]*|manhood with\s*)(.*)$', rest, re.I)
        if guys_part or re.search(r'\b(gordon|nate|bruce|nickell|woodall)\b', rest, re.I):
            g = base_entry('guys')
            g['course'], g['pillar'] = 'Purity & Addiction', 'leadership'
            if guys_part:
                gtext = guys_part.group(2)
                rest = rest[:guys_part.start()]
                if guys_part.group(1).lower().startswith('manhood'):
                    gtext = 'Manhood with ' + gtext
            else:
                gtext = rest
                rest = re.sub(r'\b(and|&)?\s*(gordon|nate|bruce)\b[\w ]*', ' ', rest, flags=re.I)
            out.append(finish(g, gtext, status, marks))
        rest = re.sub(r'^\.\s*', '', rest.strip())
        rest = re.sub(r'\b(Amy Bruton|Amy)\b\.?', 'with Amy Bruton', rest, count=1) if re.search(r'\bAmy\b', rest) and not re.search(r'with Amy', rest) else rest
        out.insert(0, finish(girls, rest, status, marks))
        return out

    # Special topics: "ST: <name> ..."
    m = re.match(r'^(?:st|special topic)\s*:?\s*(.*)$', t, re.I) if re.match(r'^(st|special topic)\b', low) else None
    if m:
        name, tail = special_topic_name(m.group(1))
        e = base_entry(audience)
        e['course'], e['pillar'] = name or 'Special Topic', 'special'
        if re.match(r'^pur[ti]+y\b', name, re.I):
            e['course'], e['pillar'] = 'Purity & Addiction', 'leadership'
        e = finish(e, tail, status, marks)
        if not name:
            warn(f'Special topic with no name: {text!r}')
        out.append(e)
        return out
    for rx, course in SPECIAL_RULES[1:]:
        m = re.match(rx, t, re.I)
        if m:
            e = base_entry(audience)
            e['course'], e['pillar'] = course, 'special'
            out.append(finish(e, t[m.end():], status, marks))
            return out

    # Catalog courses by prefix
    for rx, course, pillar, category in COURSE_RULES:
        m = re.match(rx, t, re.I)
        if m:
            rest = t[m.end():]
            if course == 'Cultural Engagement':
                rest = re.sub(r'^\s*or\b', 'or', rest, flags=re.I)
            # "Rosegate Farm (LO5/LA1) Group A Shiloh Field (LO1/LA2) Group B ..." -> one entry per group
            tokens = list(GROUP_RE.finditer(rest))
            if pillar == LS and len(tokens) >= 2:
                pieces, last = [], 0
                for tk in tokens:
                    pieces.append(rest[last:tk.end()])
                    last = tk.end()
                if rest[last:].strip():
                    pieces[-1] += rest[last:]
                for piece in pieces:
                    e = base_entry(audience)
                    e['course'], e['pillar'], e['category'] = course, pillar, category
                    out.append(finish(e, piece, status, marks))
                return out
            e = base_entry(audience)
            e['course'], e['pillar'], e['category'] = course, pillar, category
            out.append(finish(e, rest, status, marks))
            return out

    # Life-skills cells without a category prefix (girls' columns): keyword decides
    for rx, course, category in KEYWORD_CATEGORY:
        if re.search(rx, low):
            e = base_entry(audience)
            e['course'], e['pillar'], e['category'] = course, LS, category
            rest = t
            # "Baking-Debbie Wright", "Woodworking Chris Morgan"
            pm = re.match(r'^([A-Za-z &]+?)\s*[-–]\s*(' + PERSON_RE + r')\b(.*)$', rest)
            if not pm:
                pm = re.match(r'^([A-Za-z]+)\s+(' + PERSON_RE + r')\b(.*)$', rest)
            if pm and not re.search(rx, pm.group(2).lower()) and not re.search(r'project|class|basics', pm.group(2).lower()):
                rest = pm.group(1) + ' w/ ' + pm.group(2) + ' ' + pm.group(3)
            out.append(finish(e, rest, status, marks))
            return out

    # Modesty etc.: "<Topic> with <Person>" is a special topic
    pm = re.match(r'^([A-Za-z ,&\-]+?)\s+(?:with|w/|w)\s+(' + PERSON1_RE + r')\b(.*)$', t)
    if pm and len(pm.group(1)) <= 40:
        e = base_entry(audience)
        e['course'], e['pillar'] = clean_title(pm.group(1)), 'special'
        out.append(finish(e, 'w/ ' + pm.group(2) + ' ' + pm.group(3), status, marks))
        return out

    # Course name somewhere inside the text ("Xite - Life Leadership", "GIRLS ONLY: Ministry Investment")
    best = None
    for rx, course, pillar, category in COURSE_RULES:
        if course in ('Home', 'Open', 'Biblical Exposition', 'Outdoors', 'Communication', 'Study Hall'):
            continue
        m = re.search(rx.lstrip('^'), t, re.I)
        if m and (best is None or m.start() < best[0].start()):
            best = (m, course, pillar, category)
    if best:
        m, course, pillar, category = best
        e = base_entry(audience)
        e['course'], e['pillar'], e['category'] = course, pillar, category
        rest = clean_title(t[:m.start()]) + ' ' + t[m.end():]
        out.append(finish(e, rest, status, marks))
        return out

    # Generic events (tours, field trips, conferences...) named from the cell text
    for rx, course, pillar in WEAK_EVENT_RULES:
        if re.search(rx, low):
            e = base_entry(audience)
            e['pillar'] = pillar
            loc, body = extract_location(t)
            names, body, _ = extract_staff(body)
            lo_la, body = extract_lola(body)
            e['course'] = clean_title(body).rstrip('?') or clean_title(t)
            if e['course'].isupper():
                e['course'] = e['course'].title()
            rest = ('w/ ' + ', '.join(names) if names else '') + (' @ ' + loc if loc else '')
            e = finish(e, rest, status, marks)
            e['lo_la'] = lo_la
            out.append(e)
            return out

    # Fallback: unknown text becomes its own course (after pulling out lead, location, codes)
    e = base_entry(audience)
    e = finish(e, t, status, marks)
    body = e['title']
    head = body.split(':', 1)[0]
    name = clean_title(head if len(head) <= 40 else body[:40]).rstrip('?').strip()
    if name.isupper():
        name = name.title()
    e['course'] = name or 'Untitled'
    e['title'] = clean_title(body[len(head) + 1:]) if len(head) <= 40 and ':' in body else ''
    out.append(e)
    warn(f'Unknown course text, made course {name!r}: {ws_collapse(text)[:90]!r}')
    return out


def parse_cell(text, audience):
    """Cell text -> list of entry dicts (several when the cell holds more than one session)."""
    t = ws_collapse(text)
    if not t or not re.search(r'[A-Za-z0-9]', t):
        return []
    # "8-9 am Chapel 9-10:30 am Bible": split on leading time ranges when there are two or more
    pieces = TIME_SPLIT_RE.split(t)
    # pieces = [before, time1, text1, time2, text2, ...]
    times = pieces[1::2]
    lead_text = ws_collapse(pieces[0])
    if times and (lead_text == '' or re.match(r'^(guys|girls)( only)?:?$', lead_text, re.I)):
        out = []
        for i, tm in enumerate(times):
            seg = ws_collapse(pieces[2 + 2 * i])
            seg = re.sub(r'^[:;,.\s]+', '', seg)
            if not seg:
                continue
            aud = audience
            if lead_text.lower().startswith('guys'):
                aud = 'guys'
            elif lead_text.lower().startswith('girls'):
                aud = 'girls'
            for e in parse_segment(seg, aud, ws_collapse(tm)):
                e['raw'] = t
                out.append(e)
        if out:
            return out
    out = parse_segment(t, audience)
    for e in out:
        e['raw'] = t
    return out


# ----------------------------------------------------------------------------------------------
# Sheet walk
# ----------------------------------------------------------------------------------------------
def main():
    if len(sys.argv) != 5:
        sys.exit(__doc__)
    path, sheet, out_path, label = sys.argv[1:]
    years = year_pair(label)
    wb = openpyxl.load_workbook(path, data_only=True)
    if sheet not in wb.sheetnames:
        sys.exit(f'No sheet {sheet!r}; have {wb.sheetnames}')
    ws = wb[sheet]

    # merged ranges: top-left -> (rows, cols); any covered cell -> top-left
    merged = {}
    covered = {}
    for r in ws.merged_cells.ranges:
        merged[(r.min_row, r.min_col)] = (r.max_row - r.min_row + 1, r.max_col - r.min_col + 1)
        for rr in range(r.min_row, r.max_row + 1):
            for cc in range(r.min_col, r.max_col + 1):
                covered[(rr, cc)] = (r.min_row, r.min_col)

    # header row: the cell that reads "Date"
    header_row = date_col = topic_col = week_col = None
    for row in ws.iter_rows(min_row=1, max_row=10):
        for c in row:
            v = ws_collapse(c.value or '')
            if v.lower() == 'date' and header_row is None:
                header_row, date_col = c.row, c.column
        if header_row:
            for c in row:
                v = ws_collapse(c.value or '').lower()
                if v == 'topic':
                    topic_col = c.column
                if v in ('gap week', 'week'):
                    week_col = c.column
            break
    if not header_row:
        sys.exit('Could not find the header row (a cell reading "Date")')

    # slots: header cells right of Date until the GUYS/GIRLS comment columns
    slots = []
    comment_cols = {}
    col = date_col + 1
    while col <= ws.max_column:
        v = ws_collapse(ws.cell(header_row, col).value or '')
        up = v.upper()
        if up in ('GUYS', 'GIRLS', 'COMMENTS', 'COED'):
            comment_cols[col] = up.lower() if up in ('GUYS', 'GIRLS') else 'coed'
            col += 1
            continue
        if not v:
            col += 1
            continue
        rows_, cols_ = merged.get((header_row, col), (1, 1))
        start, end = parse_time_range(v)
        if not start:
            warn(f'Slot header without a time: {v!r}')
        slots.append({
            'key': get_column_letter(col), 'label': nice_slot_label(v), 'audience': slot_audience(v),
            'start_time': start, 'end_time': end, 'cols': [get_column_letter(c) for c in range(col, col + cols_)],
            '_cols': list(range(col, col + cols_)),
        })
        col += cols_
    if not slots:
        sys.exit('No slot headers found')
    slot_cols = [c for s in slots for c in s['_cols']]
    col_slot = {c: i for i, s in enumerate(slots) for c in s['_cols']}
    last_slot_col = max(slot_cols)

    # rows: date, week, topic
    dates = {}
    weeks = {}
    last_row = ws.max_row
    for r in range(header_row + 1, last_row + 1):
        d = parse_date(ws.cell(r, date_col).value, years)
        if d is None and ws.cell(r, date_col).value not in (None, ''):
            warn(f'Unparsed date in row {r}: {ws.cell(r, date_col).value!r}')
        dates[r] = d
    if week_col:
        for r in range(header_row + 1, last_row + 1):
            if (r, week_col) in covered and covered[(r, week_col)] != (r, week_col):
                continue
            v = ws.cell(r, week_col).value
            if v in (None, ''):
                continue
            try:
                n = int(float(str(v).strip()))
            except ValueError:
                continue
            rows_, _ = merged.get((r, week_col), (1, 1))
            for rr in range(r, r + rows_):
                weeks[rr] = n
    week_one = None
    for r in sorted(weeks):
        if weeks[r] == 1 and dates.get(r):
            week_one = dates[r]
            break
    if not week_one:
        warn('Could not find the row where GAP Week = 1')

    def date_for(r):
        """Date of a row, computing from the nearest dated row above when the row is blank."""
        if dates.get(r):
            return dates[r]
        rr = r
        while rr > header_row and not dates.get(rr):
            rr -= 1
        if dates.get(rr):
            return dates[rr] + datetime.timedelta(days=r - rr)
        return None

    # blocks from the Topic column
    blocks = []
    if topic_col:
        for r in range(header_row + 1, last_row + 1):
            if (r, topic_col) in covered and covered[(r, topic_col)] != (r, topic_col):
                continue
            v = ws.cell(r, topic_col).value
            if v in (None, ''):
                continue
            lab = ws_collapse(v)
            if lab == 'y':
                warn("Block label 'y' read as 'Workplace'")
                lab = 'Workplace'
            rows_, _ = merged.get((r, topic_col), (1, 1))
            sd, ed = date_for(r), date_for(r + rows_ - 1)
            if sd and ed:
                blocks.append({'label': lab, 'start_date': sd, 'end_date': ed})
        for i, b in enumerate(blocks):
            if i + 1 < len(blocks):
                nxt = blocks[i + 1]['start_date'] - datetime.timedelta(days=1)
                if nxt > b['end_date']:
                    b['end_date'] = nxt   # the label cell is not always merged down to the next block
            b['start_date'], b['end_date'] = b['start_date'].isoformat(), b['end_date'].isoformat()

    # entries
    entries = []
    cats = {'phys ed': 'Phys Ed', 'outdoors': 'Outdoors', 'automotive': 'Automotive', 'home': 'Home',
            'creation care': 'Creation Care', 'artisanship': 'Artisanship', 'vocation': 'Vocation'}
    for r in range(header_row + 1, last_row + 1):
        sd = date_for(r)
        if not sd:
            continue
        PARSE_CONTEXT['category'] = None
        for b in blocks:
            if b['start_date'] <= sd.isoformat() <= b['end_date']:
                PARSE_CONTEXT['category'] = cats.get(b['label'].lower())
        comments = {}
        for c, aud in comment_cols.items():
            v = ws.cell(r, c).value
            if v not in (None, '') and not ((r, c) in covered and covered[(r, c)] != (r, c)):
                comments.setdefault(aud, []).append(ws_collapse(v))
        for c in slot_cols:
            if (r, c) in covered and covered[(r, c)] != (r, c):
                continue  # inside a merge, handled at its top-left
            v = ws.cell(r, c).value
            if v in (None, ''):
                continue
            rows_, cols_ = merged.get((r, c), (1, 1))
            ed = date_for(r + rows_ - 1) or sd
            si = col_slot[c]
            covered_slots = sorted({col_slot[cc] for cc in range(c, c + cols_) if cc in col_slot})
            span = len(covered_slots)
            all_day = (c + cols_ - 1) >= last_slot_col and span >= 2 and si == 0
            audience = slots[si]['audience']
            for e in parse_cell(v, audience):
                e['start_date'] = sd.isoformat()
                e['end_date'] = ed.isoformat()
                if e['pillar'] in ('event', 'break') or all_day:
                    e['slot'] = None
                    e['slot_span'] = 1
                else:
                    e['slot'] = slots[si]['key']
                    e['slot_span'] = span
                # comments column text for this row and side
                extra = []
                if e['audience'] == 'coed':
                    for aud in ('guys', 'girls', 'coed'):
                        extra += comments.get(aud, [])
                else:
                    extra += comments.get(e['audience'], []) + comments.get('coed', [])
                if extra:
                    e['comment'] = '; '.join([x for x in [e['comment']] + extra if x])
                entries.append(e)

    for s in slots:
        del s['_cols']
    data = {
        'label': label,
        'week_one_starts': week_one.isoformat() if week_one else None,
        'slots': slots,
        'blocks': blocks,
        'entries': entries,
        'warnings': WARNINGS,
    }
    with open(out_path, 'w') as f:
        json.dump(data, f, indent=1, default=str)
    courses = {}
    for e in entries:
        courses.setdefault((e['course'], e['pillar']), 0)
        courses[(e['course'], e['pillar'])] += 1
    print(f'{label} {sheet!r}: {len(slots)} slots, {len(blocks)} blocks, {len(entries)} entries, '
          f'{sum(1 for e in entries if e["staff"])} with staff, {len(courses)} courses, {len(WARNINGS)} warnings')


if __name__ == '__main__':
    main()
