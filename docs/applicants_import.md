# Applicants import JSON

`python3 tools/applicants_to_json.py "<year workbook.xlsx>" <out.json> <year label>` reads the applicant
tab(s) of one yearly database workbook and writes JSON for `php spark gap:import-applicants <json> --year <label>`.
Dates are `YYYY-MM-DD` or null.

Tabs by year: 2026-27 has one `Applicants` tab with `GUY APPLICANTS` / `GIRL APPLICANTS` section rows;
2025-26 and 2024-25 have `Guys` and `Girls` tabs; 2023-24 has `2023-24 GUY` and `2023-24 GIRL`
(ignore `Copy of …` tabs). Columns are found by header text (row 1), with aliases per year.

```json
{
  "label": "2026-27",
  "applicants": [
    {
      "submission_order": 2,                 // the first column when numeric, else null
      "first_name": "Jonathan", "preferred_name": "Jon", "last_name": "Example",   // "Jonathan (Jon)" splits
      "gender": "M",                         // from the tab / section
      "email": "x@y.com", "phone": "…", "address": "one line", "birthday": "2007-01-01",
      "mom_email": "…", "dad_email": "…",
      "tshirt_size": "Large", "placement_preference": "Elementary",
      "allergies_medical": "…", "cleared_to_drive": "Cleared to Drive", "auto_insurance": "…",
      "housing_preference": "…", "tuition_note": "4000",
      "notes": "Parents are divorced",       // the Notes/NOTES column
      "status": "accepted",                  // accepted | declined | waitlisted | withdrawn (see rules)
      "steps": {                             // only the steps the sheet says something about
        "applied":       {"done_on": "2025-11-13", "note": null},
        "preview":       {"done_on": null, "note": "Attended January 2026 Preview, all 3 days"},
        "personal_ref":  {"done_on": null, "note": "Reference name, relationship, contact…", "done": true},   // done = green fill
        "spiritual_ref": {"done_on": null, "note": "…", "done": true},
        "interview":     {"done_on": "2026-02-02", "note": "Monday, February 2nd at 10:00 AM/Teams", "done": true},
        "decision":      {"done_on": "2026-02-03", "note": "Acceptance email sent to 2/3/26", "done": true},
        "commitment":    {"done_on": "2026-02-04", "note": null, "done": true},
        "background":    {"done_on": null, "note": "12/1/2025; Cleared to serve on 2/26/26; Marked \"Placed\" on 2/26/26;", "done": true},
        "books":         {"done_on": null, "note": "Just Do Something ordered 5/21", "done": true},
        "canvas":        {"done_on": null, "note": "yes", "done": true}
      },
      "raw": {"Step 2:  Interview (Date/Time/Method)": "…"}   // every non-empty cell by header, for the importer's fallback note
    }
  ],
  "warnings": ["…"]
}
```

Step keys and the headers that feed them (any year):
- `applied`: "Step 1 : Application" (a date, or a link/text → note)
- `preview`: "Invited to Preview? Attended?", "Preview Week Communicated"
- `video`: "Video Received?"; `newsletter`: "GAP Newsletter"
- `personal_ref`: "Personal Reference- highlighted in green if received"; `spiritual_ref`: "Spiritual Reference- …";
  2024-25 has one column "Reference Reports received and reviewed" → put it on `personal_ref` with the text
- `interview`: "Step 2:  Interview (Date/Time/Method)", "Step 3: Personal Interview Completed"
- `decision`: "Interview Result/Acceptance Notification Sent", "Acceptance Notification sent"
- `commitment`: "Commitment Letter Signed", "Commitment Letter received"
- `background`: 'Response marked as "Ministry Leader Reviewed" …', "SAAT request made/Opportunity Response Created", "Driver Opportunity Completed" (join with "; ")
- `books`: "Books ordered and sent"; `canvas`: "Added to Canvas Course"; `dates_doc`: "Important Dates and Information Document Sent"
A step is `done` when its cell is green-filled (B6D7A8 / D9EAD3), is a date, or its text starts with yes/done/complete.
`done_on` is the cell's date when the cell is a date, else the first m/d/yy date found in the text.

Status rules: green-filled name cell → accepted; "Not accepted", "Decline", "declined", "not placed" in the
decision or commitment cell → declined; "waitlist" anywhere in the row → waitlisted; 2024-25 "Process Status"
column text wins when it names one of the four; otherwise `withdrawn` with a warning naming the person.
The importer re-checks: an applicant whose name or email matches a student of that year becomes accepted.
