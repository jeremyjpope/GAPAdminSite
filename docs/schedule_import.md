# Schedule map import JSON

`tools/map_to_json.py "<Life Skills & Special Topics Maps- CURRENT.xlsx>" "<sheet name>" <out.json> <year label>`
turns one "LS & ST Map" tab (one row per calendar day, one column per time slot) into JSON that
`php spark gap:import-schedule <json> --year <label>` loads. All dates are `YYYY-MM-DD`, times `HH:MM`.

```json
{
  "label": "2026-27",
  "week_one_starts": "2026-08-17",          // date of the row where the GAP Week column first reads 1
  "slots": [                                // from the header row, left to right (sort order = position)
    {"key": "E", "label": "Coed Morning 7:30-9:00", "audience": "coed", "start_time": "07:30", "end_time": "09:00", "cols": ["E"]},
    {"key": "K", "label": "Guys Life Skill 1-3:30", "audience": "guys", "start_time": "13:00", "end_time": "15:30", "cols": ["K", "L"]}
  ],
  "blocks": [                               // column A merged labels (Kick-Off, Orientation, Leadership, Bible, ...)
    {"label": "Orientation", "start_date": "2026-08-17", "end_date": "2026-08-30"}
  ],
  "entries": [
    {
      "start_date": "2026-09-15", "end_date": "2026-09-15",
      "slot": "K",                          // slot key, or null for an all-day / multi-day block (break, trip, event)
      "slot_span": 1,                       // >1 when the cell was merged across more than one slot header
      "course": "Artisanship",              // course name to match or create
      "pillar": "lifeskills",               // bible|worldview|leadership|lifeskills|special|formation|event|break|other
      "category": "Artisanship",            // life-skills category or null: Phys Ed, Outdoors, Automotive, Home, Creation Care, Artisanship, Vocation
      "audience": "guys",                   // coed|guys|girls (the slot's audience unless the cell says otherwise)
      "title": "Woodworking",               // the specific session detail, may be ""
      "group": "A",                         // A|B|C|D|"A & B"|null
      "lo_la": "LO1/LA1,3,4,5",             // learning outcome / activity codes as written, or null
      "staff": [{"name": "Chris Morgan", "role": "instructor"}],
      "location": "his shop",
      "status": "planned",                  // planned|confirmed|missed|cancelled
      "comment": "Confirmed KK",            // confirmation marks plus the Comments column text for that row/side
      "raw": "ARTISANSHIP: LO1/LA1,3,4,5 Group A Woodworking w/ Chris Morgan @ his shop"
    }
  ],
  "warnings": ["..."]
}
```

Rules the parser follows:
- A cell merged across several day rows becomes one entry with `start_date`/`end_date` covering them.
  A cell merged across every slot column (breaks, trips, move-in weekend) has `slot: null`.
- The Guys and Girls Life Skill headers are each one slot spanning two sheet columns; two cells
  under one of them are two entries in the same slot (usually Group A and Group B). A cell merged
  across both columns is one entry.
- "Manhood/Womanhood" in a coed slot becomes two entries: Manhood (guys) and Womanhood (girls).
  "Relationships & Dating ..." in a coed slot becomes Relationships & Sexuality (girls) and
  Purity & Addiction (guys) when the cell mentions the guys' teachers, otherwise one coed entry.
- `ST: <name> w/ <person>` is a Special Topic course named `<name>` with `<person>` as instructor.
- Known prefixes map to catalog courses: PhysEd/Phys Ed → Physical Fitness (Phys Ed); OUTDOORS →
  Outdoors; HOME → Home; ARTISANSHIP → Artisanship; AUTOMOTIVE → Automotive; CREATION CARE →
  Creation Care; VOCATION/Vocational Mission → Vocational Mission (Vocation); Chapel; Calvin
  Devotional → Devotional; Bible → Biblical Exposition; Bible Study Methods; Worldview → Christian
  Worldview; Cultural Engagement...; Church History; Life Leadership → Biblical Leadership;
  Ministry Investment; Church Service & Ministry Investment; Sabbath; Open.
- Unrecognised text becomes a course named from the text before the first ":" or the first 40
  characters, pillar `other`, and a warning is recorded.
