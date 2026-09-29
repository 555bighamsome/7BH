# 7BH

Interface and data-collection code for a behavioural study of how people search across movement-direction and robot-type representations when constructing shared coordination rules.

## Local preview

Run a static server from this directory:

```bash
python3 -m http.server 8767 --bind 127.0.0.1
```

Then open:

```text
http://127.0.0.1:8767/routes/consent.html?preview=1
```

Preview sessions remain in browser storage and do not write to the study server.

## Study flow

The standalone pages cover consent, reminder, tutorial, comprehension, eleven coordination tasks, debrief, and completion. Formal server assignment balances participants across the all-local, early-jump, and late-jump conditions.

## Data safety

Participant events are not stored in this repository or under the public web directory. In deployment, the PHP endpoints expect pre-created private directories outside `public_html`. See `DATA_COLLECTION.md` for the event schema, assignment method, and deployment checks.

