# MediTrack

RFID-based patient, staff and inventory tracking system for a hospital ward, built on ESP32 + RC522 readers and a PHP/MySQL backend.

## What it does

Every patient, staff member and piece of equipment/medicine wears or carries an RFID tag. ESP32 boards with MFRC522 readers sit at fixed points (general ward, ICU, zone A, zone B) and post every tag scan to a PHP backend over HTTP. The backend resolves the UID against the database, updates where that patient/item was last seen, logs the movement, and runs a background job that raises tasks and alerts for things that need attention: overdue medication, a patient not seen recently (fall/elopement risk), a reader that's gone silent, or a drug nearing expiry or under recall.

The web app on top of that is a dashboard: active patients, inventory, medication schedule and compliance, IV drip tracking with ETA, vitals with automatic threshold checks, a reader/zone activity heatmap, an audit log, and a caretaker portal that lets a family member log in (or use a time-limited token link) to see a specific patient's status without touching the rest of the system.

## Why it exists

This started as a hospital ward safety and asset-tracking exercise: knowing where a patient is at all times (important for fall-risk and elopement-risk patients), knowing where a piece of equipment or a batch of medicine physically is, and catching problems (missed doses, expired stock, dead readers) automatically instead of relying on someone remembering to check.

## Architecture

```
RFID tag (patient / staff / item)
        │  tapped near a reader
        ▼
ESP32 + MFRC522 reader (per zone/ward)
        │  HTTP GET scan.php?uid=...&readerid=...&apikey=...
        │  HTTP GET heartbeat.php (every 10s, so the backend knows the reader is alive)
        ▼
PHP backend (meditrack_compact/)
  ├─ core.php        shared DB helpers, schema-tolerant column resolution, alert/task engine
  ├─ scan.php         resolves UID → patient/staff/item, logs movement, updates last-seen
  ├─ heartbeat.php     reader liveness
  ├─ api.php           JSON endpoints (notifications, DB backup)
  ├─ live_updates.php  polled by the dashboard every 5s for live KPIs/alerts/moves
  ├─ index.php         the whole dashboard (patients, inventory, meds, IV, vitals, alerts, workflow, staff, audit, reports)
  ├─ caretaker_portal.php / caretaker_login.php   family-facing read-only view
  └─ offline_queue.php scans that arrive while a patient/item is unresolved get queued instead of dropped
        │
        ▼
MySQL (meditrack_compact.sql / database.sql)
```

`runSystemJobs()` in `core.php` runs on every page load and evaluates: overdue medications (30+ min late → high-priority task), patients unseen past their configured safety threshold, offline readers (no heartbeat in 10 min), and medicine that's recalled or expiring within 15 days. It writes to `workflowtasks` and `alerts` rather than emailing/paging anyone, since this is a single-ward prototype, not a production alerting system.

Column names are resolved through helper functions like `patient_cols()` / `item_cols()` that check which of two possible naming conventions (`snake_case` vs `nocase`) a table actually has and use whichever exists. That's a leftover from the schema evolving across iterations rather than a deliberate abstraction, it's brittle but it works and let old and new database dumps stay compatible with the same code.

## Repo layout

- `meditrack_compact/` — the current, final version of the system (single PHP file per page, `core.php` for shared logic)
- `meditrack_compact.sql` — matching database schema/dump
- `general_ward_code/`, `icu_code/` — ESP32 firmware for the ward and ICU readers
- `get_rfid/` — standalone sketch to read out a tag's UID over Serial, used to register new tags before wiring them into the system
- `iteration 1/` — an earlier, more sprawling version of the backend (separate files per concern, `zona_a_code`/`zona_b_code` readers) kept for reference, not the version to deploy
- `rfid_tags.txt` — UID log captured from `get_rfid` while registering tags
- `docs/meditrack_report.pdf` — write-up submitted for the course this was built for, covers the same architecture and schema as this README plus the hospital-workflow motivation in more detail

## Running it

1. **Backend**: install a LAMP/XAMPP-style stack (PHP 8 + MySQL/MariaDB), import `meditrack_compact.sql` into a database named `meditrack_compact`, and serve the `meditrack_compact/` folder. Default admin login is created by the SQL dump (`admin`, password is whatever was set at export time, i.e. change it before using this anywhere real).
2. **`config.php`**: set `$DB_HOST`/`$DB_USER`/`$DB_PASS`/`$DB_NAME` to match your MySQL instance.
3. **Firmware**: open `general_ward_code.ino` / `icu_code.ino` in the Arduino IDE with the ESP32 board package and the `MFRC522` library installed. Wire an RC522 to the pins defined at the top of the file (SS=5, RST=27 for the ESP32 boards used here), set `WIFI_SSID`, `WIFI_PASS`, `SERVER_IP` (the machine running the PHP backend), `PROJECT_DIR`, `READER_ID` and `API_KEY`, then flash. Each reader needs a unique `READER_ID`/`API_KEY` pair that matches a row in the `locations` table.
4. Open `index.php` in a browser, log in, and scans from the readers should start showing up on the dashboard within a few seconds (the frontend polls `live_updates.php` every 5s).

## Known limitations

- WiFi credentials and per-reader API keys are hardcoded in the `.ino` firmware rather than provisioned at flash time, fine for a single-ward prototype on a trusted network, not something to reuse as-is on a production network.
- `config.php` ships with a blank root MySQL password, which is the standard local XAMPP default, not a real credential, but it needs to be set explicitly before deploying anywhere beyond localhost.
- Alerts and tasks are surfaced only in the dashboard UI (polling), there's no push notification, SMS or pager integration.
- `iteration 1/` is not maintained in parallel with `meditrack_compact/`, treat it as history rather than a second supported deployment.
- The unresolved-tag / offline-queue path (`offline_queue.php`) exists in the schema and helper functions but isn't fully wired into every scan path, some untracked UIDs currently just raise an "unknown tag" alert instead of queuing.

No automated tests, this was validated by scanning real tags against the running dashboard and checking that patient location, alerts, and the medication/vitals/IV tables updated correctly.

## Data model

The schema (see `meditrack_compact.sql`) centers on a `tags` table (UID → patient/staff/item reference) and a `movements` log that every scan appends to. `locations` maps `reader_id` to a physical zone and holds the per-reader `api_key` checked in `scan.php`. Alerts, tasks, medication schedules, IV records and vitals each have their own table, all keyed back to the patient. Because `meditrack_compact/` and `iteration 1/` evolved their column naming independently (one iteration used `snake_case`, the other a flatter `nocase` convention), `core.php`'s `patient_cols()`/`item_cols()` helpers probe the live schema at runtime and adapt rather than assuming one naming convention, that's what lets the same `meditrack_compact/` code run against either a fresh `meditrack_compact.sql` import or an older dump without a migration step.

## References

- [ESP-MFRC522 library](https://github.com/miguelbalboa/rfid) — Arduino RFID reader driver used in all three firmware sketches
- `docs/meditrack_report.pdf` — full project write-up (problem framing, hospital workflow, schema, and results from the course submission)
