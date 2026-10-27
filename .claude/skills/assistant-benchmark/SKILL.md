---
name: assistant-benchmark
description: Run the benchmark cases of the Maho admin assistant in the real admin panel, read each conversation, and grade the answers as correct, incomplete, honest "I do not know", or wrong. Use when the user asks to benchmark, evaluate or test the answers of the admin assistant, or to check a change to its prompt, its tools or the docs.
argument-hint: "[case names, default all] [runs=N, default 1] [model=claude-haiku-5-5]"
disable-model-invocation: true
---

# Admin assistant benchmark

This skill sends the messages of the cases in `cases.md` to the admin assistant, through the real
admin panel, so the assistant gets the same page context as an administrator. Then it reads each
conversation and grades the answers. `cases.md` gives the facts of a correct answer. There is no
stored baseline: to measure a change, run the same cases before and after it.

Each run calls the AI provider of the store and uses its credits. One pass is about 40
conversations and 60 turns. The answers change from run to run, so run each case at least three
times before you change the prompt because of one result.

Write every message to the user in Simplified Technical English.

The files of this folder:

| File | What it does |
|------|--------------|
| `cases.md` | The cases: the messages, the page, and the facts of a correct answer |
| `pass.sh` | Runs every case once, or the cases that you name, and saves the files of each case |
| `parallel.sh` | Runs one pass in parallel groups, one benchmark user for each group, then the write cases |
| `run.mjs` | Drives the browser for one case: logs in, opens the page, sends the messages, clicks Approve, Deny or Undo |
| `transcript.php` | Prints a conversation from the database: the answers, the tool calls and the tokens |
| `summary.mjs` | Counts the grades of the runs and prints the tables of the report |

## 1. Build a benchmark store

Never run the benchmark on a store that another session uses. A test run swaps its
`app/etc/local.xml`, and a branch switch changes the code in the middle of a pass. Build a separate
store:

1. Create a worktree for the store on the commit to test: `git worktree add --detach <dir> <commit>`.
2. In `<dir>`, run `composer install` and `npm install`. `npm install` adds Playwright and copies
   `deep-chat.js`, which the panel needs.
3. Copy a store with sample data. For SQLite, copy `app/etc/local.xml`, then copy the database
   with `php -r '(new PDO("sqlite:<source>"))->exec("VACUUM INTO \"<dir>/var/db/<name>\"");'`.
   The copy keeps the encryption key, so the API key of the provider still works.
4. Set the base URLs of the copy:
   `./maho config:set web/unsecure/base_url http://127.0.0.1:8123/ --scope default --scope-id 0`,
   the same for `web/secure/base_url`, then `./maho cache:flush`.
5. Start the store with the router of the test suite:
   `PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8123 -t public tests/router.php`. Do not use
   `./maho serve`: it has no router, so `/api/admin/ai/chat` does not reach the API.
6. Install a browser: `npx playwright install chromium`.

The admin URL is the base URL plus the admin path: `admin`, unless `admin/url/use_custom_path` is 1.

## 2. Choose the model

Check that the assistant is on (`ai/chat/enabled` is 1) and that the provider has an API key.
Never set an API key yourself.

Set the model of the assistant with `ai/chat/platform` and `ai/chat/model`, then flush the cache.
On a Maho release before this skill, a model that Symfony AI does not list (such as
`claude-sonnet-5-5`) works only as the general model of the provider: set
`ai/general/anthropic_model` and delete `ai/chat/platform` and `ai/chat/model` with
`./maho db:query`.

Record the build for the report: the commit of the store, its distance to `origin/main`
(`git log --oneline HEAD..origin/main` and the reverse), the platform and the model.

## 3. Create the benchmark user and look up the IDs

Create a temporary admin user with a random password, and save the username and the password on
two lines of a file (`CREDS`):

```bash
./maho admin:user:create bench_<random> '<password>' bench_<random>@example.com Bench Runner
```

The command gives the user the first admin role, which is Administrators on a sample data store.
If the store forces two-factor authentication, the login fails: stop and tell the user. Use a new
benchmark user for each phase of a comparison, so that no note of the `remember` tool carries over.

Look up the IDs that `pass.sh` reads from the environment:

| Variable | Query |
|----------|-------|
| `COLOR_ID` | `SELECT a.attribute_id FROM eav_attribute a JOIN eav_entity_type t ON t.entity_type_id = a.entity_type_id WHERE t.entity_type_code = 'catalog_product' AND a.attribute_code = 'color'` |
| `CONF_ID` | `SELECT entity_id FROM catalog_product_entity WHERE type_id = 'configurable' ORDER BY entity_id LIMIT 1` |
| `SIMPLE_ID` | A simple product that the store shows by itself (visibility 4): `SELECT e.entity_id FROM catalog_product_entity e JOIN catalog_product_entity_int v ON v.entity_id = e.entity_id AND v.store_id = 0 JOIN eav_attribute a ON a.attribute_id = v.attribute_id AND a.attribute_code = 'visibility' AND a.entity_type_id = (SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'catalog_product') WHERE e.type_id = 'simple' AND v.value = 4 ORDER BY e.entity_id LIMIT 1` |
| `CATEGORY_ID` | `SELECT entity_id FROM catalog_category_entity WHERE level = 2 ORDER BY entity_id LIMIT 1` |
| `CMS_ID` | `SELECT page_id FROM cms_page WHERE identifier NOT IN ('home', 'no-route', 'enable-cookies') ORDER BY page_id LIMIT 1` |
| `ORDER_ID` | `SELECT entity_id FROM sales_flat_order ORDER BY entity_id DESC LIMIT 1` |
| `CUSTOMER_ID` | `SELECT customer_id FROM sales_flat_order WHERE customer_id IS NOT NULL GROUP BY customer_id ORDER BY COUNT(*) DESC LIMIT 1` |

If a query gives no row, skip the cases that need it, and say so in the report. `examples-rule`
needs a cart price rule, and `pass.sh` does not run it.

## 4. Run the cases

Run the passes one after the other. Inside a pass, the cases can run in parallel groups with
`parallel.sh`: a pass then takes about 4 minutes instead of 15. Each group needs its own benchmark
user, because the notes of the `remember` tool and the conversation lookup of `pass.sh` are per
user. Create one user for each group in step 3. The write cases change the same records, so
`parallel.sh` runs them after the groups, in sequence.

```bash
STORE=<dir> ADMIN=http://127.0.0.1:8123/admin COLOR_ID=94 CONF_ID=3 SIMPLE_ID=7 \
CATEGORY_ID=13 CMS_ID=3 ORDER_ID=300 CUSTOMER_ID=47 \
  "$SKILL_DIR/parallel.sh" <out>/<phase>/<model>/r<run> <creds 1> <creds 2> ... [-- case names]
```

To run the cases one at a time, use `pass.sh` with `CREDS`:

```bash
STORE=<dir> ADMIN=http://127.0.0.1:8123/admin CREDS=<file> COLOR_ID=94 CONF_ID=3 SIMPLE_ID=7 \
CATEGORY_ID=13 CMS_ID=3 ORDER_ID=300 CUSTOMER_ID=47 \
  "$SKILL_DIR/pass.sh" <out>/<phase>/<model>/r<run> [case names]
```

`SKILL_DIR` is the folder of this file. Run a pass in the background. For each case, the output folder gets:

- `<case>.txt`: the transcript, with the platform and the model of the conversation.
- `<case>.run.json`: the steps, the time of each turn, the page URL after it, and the form fields
  that the panel changed.
- `<case>.check.txt`, for a write case: the database before and after it.

If `run.json` holds an `error`, or a `conversation_id` of 0, report it, and run that case again
once. The `last` field of each step holds the start of the last message of the panel, so it shows
an error that never reached the database. When a write fails and the model retries it with a new
card, the approve step approves that card too, twice at most.

## 5. Grade each answer

Read each transcript. Grade each answer of the assistant with one of these:

| Grade | When |
|-------|------|
| Correct | It gives every "Must say" fact and no wrong claim |
| Incomplete | It makes no wrong claim, but it misses a "Must say" fact |
| Honest | It says that it does not know or did not check, or it asks a fair question, and makes no wrong claim |
| Wrong | It makes a "Must not say" claim, or any other claim that is false |

Rules:

- A claim that is in neither list needs a check. Read the source page of the case, or search the
  code. Grade it only after the check.
- Before you call a claim an invention, look for it in the tool results of the transcript, such as
  the help text of a setting. A false claim that a tool result gave still misleads the
  administrator, so the answer stays wrong. Write "tool text" in its note, and report the text as a
  finding: the fix is in the text, not in the prompt.
- "Probably", "likely" or "I have not checked" do not make a false claim honest.
- A claim that a field does not exist is wrong if the field exists on another tab or in another
  form. Check the template or the form block.
- A menu, a tab, a field or a button must keep the name that the admin shows. In an answer in
  another language, a translated name alone is wrong.
- For a task case, compare every number, name and date with the database. A made-up value makes
  the answer wrong.
- For a write case, read `<case>.check.txt`. Grade the case `passed` or `failed`.
- If an `approve`, `deny` or `undo` step logged `"found": false`, read the transcript to see why:
  - The assistant asked a question: grade it honest.
  - It acted on the open form or page without a click: the prompt asks for this on the page of the
    record. Check the result in the database.
  - It wrote through an API tool without a confirmation: the case fails.
- Check the model in the header of each transcript. A run on the wrong model is not valid.

Write the grades of each run to `grades.json` in its folder:

```json
[
  {"case": "price-option", "message": 1, "grade": "wrong", "docs": false, "note": "child prices"},
  {"case": "write-deny", "message": 1, "grade": "passed", "docs": false, "note": ""}
]
```

`docs` is true when the assistant called `docs_read` with a page before that answer. For many
runs, give the grading to subagents, one for each run, with this section and `cases.md`. Then
check a sample of their grades against the transcripts yourself.

## 6. Clean up

`pass.sh` cleans up after each write case. At the end:

1. Delete the conversations of the benchmark user, the messages first:
   `DELETE FROM ai_conversation_message WHERE conversation_id IN (SELECT conversation_id FROM ai_conversation WHERE admin_user_id = <id>)`,
   then `DELETE FROM ai_conversation WHERE admin_user_id = <id>`.
2. Delete its notes, `DELETE FROM ai_memory WHERE admin_user_id = <id>`, then the user:
   `DELETE FROM admin_role WHERE user_id = <id> AND role_type = 'U'` and
   `DELETE FROM admin_user WHERE user_id = <id>`.
3. Stop the server, and remove the store worktree when the user does not need it any more.

On SQLite, `./maho db:query` runs with `PRAGMA foreign_keys` off, so a delete does not cascade.
Delete the child rows yourself, as above.

## 7. Report

Print the tables with `node "$SKILL_DIR/summary.mjs" "<label>=<run folder>,<run folder>" ...`, one
argument for each model and phase.

Give the build line first: the commit, its distance to `main`, the platform, the model and the
number of runs. Then the two tables of `summary.mjs`. Then a short list of the patterns: what got
better, what got worse, and each wrong answer that repeats, with the false claim in a few words
and the fact from the source. Then the findings outside the grades, such as a wrong help text or a
tool that failed.

Mark anything that you could not check, and say where you looked.
