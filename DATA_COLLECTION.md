# Study data collection

Each participant receives one stable random study code (`session`) and one formal assignment:

- `all-local`
- `jump-2` (`early-jump`)
- `jump-5` (`late-jump`)

Formal assignment uses randomized blocks of three on the server. Each newly opened block contains one `all-local`, one `jump-2`, and one `jump-5` assignment in random order. Therefore assigned counts are equal after every complete block and differ by at most one while a block is filling. The assignment is stored against a one-way hash of the participant identifier, then carried in the URL and browser across all standalone pages. `jump-4` remains available only in researcher preview mode.

The formal recruitment link must enter through `index.html`; direct links to later pages bypass the server allocator. The allocator uses the recruitment identifier only to create a one-way assignment key, then removes that identifier from the study URL. It is not written to the behavioural event stream. Local `file://` previews use a client-side random fallback because PHP is unavailable. Attrition can still unbalance the final valid sample, so recruitment should continue until every arm reaches the same preregistered number of valid participants.

## Event stream

`data-collector.js` stores one ordered event stream for the complete session. Every event includes:

- the random study code;
- condition, schedule, stage, page, trial, task, attempt, and rule revision;
- absolute time, time since the session began, and time on the current page;
- an event-specific `detail` object.

The stream includes consent progress, tutorial navigation, comprehension responses, UI actions, every rule edit, every Run, every displayed simulation frame, task transitions, debrief responses, focus/visibility changes, and study completion.

The final questionnaire is kept as a local draft until submission. Its explicit `debrief_responses_submitted` event contains age, gender, engagement, difficulty, the participant's reported feature use, and optional strategy/feedback text. The study explanation is revealed only after the upload is acknowledged, so it cannot shape those retrospective answers. If an upload fails, the participant remains on the page and can retry. The completion page enables the Prolific return control only after the final event queue is confirmed saved.

## Persistence

During local `file://` preview, events are retained in browser storage and can be downloaded as JSON, JSONL, or CSV from the completion page.

When the interface is served over HTTP(S), `assign_condition.php` manages the locked block-randomization state and the collector posts pending events to `record_result.php`. Failed uploads are retried up to three times, and large queues are split into bounded batches. The server endpoint validates the origin, content type, payload, session code, and event structure before appending de-duplicated events.

No participant records are written inside `public_html`. The deployment uses three private locations:

- `/home/bococo81/server_data/7BH/events/` for tutorial, comprehension, task, debrief, and completion events;
- `/home/bococo81/server_data/7BH/consent/` for consent-stage events;
- `/home/bococo81/server_data/7BH/assignment/` for the hashed condition-allocation state.

Files are created with mode `0600`; the private directories must be created before recruitment and must not be included in web deployment or deletion commands.

## Formal deployment check

Before recruitment, verify all of the following on the deployed host:

1. A full session follows `routes/consent.html` -> `routes/reminder.html` -> `routes/tutorial.html` -> `routes/comprehension.html` -> `routes/task.html`; it then shows the congratulations dialog, reaches `routes/debrief.html`, and finally `routes/completion.html`.
2. Consent and response JSONL files are created in their separate private directories.
3. The response file contains tutorial, comprehension, task, debrief, and completion events.
4. Event indexes are ordered and duplicate event IDs are absent across repeated uploads.
5. Reloading a page preserves the same study code and assignment.
6. Each formal participant is assigned only to `all-local`, `jump-2`, or `jump-5`.
