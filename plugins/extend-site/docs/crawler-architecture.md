# Extend Site Crawler Architecture

This document describes the crawler module in the `extend-site` WordPress plugin. It is intended for AI agents and developers who need to understand the structure, data model, and business flow before making changes.

## Module Purpose

The crawler imports chapter content from external source URLs into an existing Story. It does not create stories. It creates Chapter posts, links them to a selected Story, tracks source URLs, prevents duplicates, and finalizes story metadata after a batch finishes.

Crawler scope is story-based:

- A source URL is considered duplicate only within the same `story_id`.
- The same source URL may be crawled for another story.
- Chapter number uniqueness is also checked within the same story.

## Main Files

| File | Responsibility |
| --- | --- |
| `includes/Crawler/CrawlerAdmin.php` | Renders the admin crawler page UI. It does not decide crawl logic. |
| `includes/Crawler/CrawlerAjax.php` | Main controller for AJAX actions: preview, start, heartbeat, process URL, stop, finalize. Contains duplicate checks and chapter creation flow. |
| `includes/Crawler/CrawlerLinkTable.php` | Database table helper for `{$wpdb->prefix}es_crawler_links`. Tracks URL crawl status per story. |
| `includes/Crawler/CrawlerLock.php` | Single-batch lock stored in WordPress options. Prevents concurrent crawler batches. |
| `includes/Crawler/Scraper.php` | Fetches and parses source HTML, extracts title/content/chapter numbers, applies cleanup rules. |
| `assets/js/backend/story-crawler.js` | Admin-side crawler workflow: URL generation, AJAX queue, retries, progress, logs, finalize summary. |
| `assets/css/backend/story-crawler.css` | Admin crawler UI styling. |
| `includes/Core/Enqueue.php` | Enqueues crawler JS/CSS only on crawler admin page and localizes AJAX config. |
| `includes/Core/Plugin.php` | Boots crawler components and runs versioned DB updates. |
| `includes/DB/DBInstaller.php` | Creates crawler table on plugin activation via `CrawlerLinkTable::create()`. |

## Admin UI Flow

The crawler page is registered by `CrawlerAdmin::register_menu()` as a submenu page:

- Parent: `extend-site-main`
- Page slug: `extend-site-crawler`
- Capability: `manage_options`

The UI collects:

- Story selection.
- URL pattern containing `{n}`.
- Chapter range: from/to.
- Number padding option.
- Preview chapter number or override preview URL.
- Post status: `draft` or `publish`.
- Title mode.
- Optional find/replace cleanup rules.
- Crawl delay.

The UI itself is passive. Processing happens through AJAX calls in `story-crawler.js`.

## JavaScript Workflow

`assets/js/backend/story-crawler.js` owns the browser-side batch workflow.

Important state fields:

- `queue`: generated list of URLs and chapter numbers.
- `index`: current queue index.
- `processed`: completed count.
- `isRunning`, `isPaused`: runtime flags.
- `batchId`: lock batch id returned by server.
- `storyId`: selected story.
- `consecutiveFailures`: used to stop after repeated failures.
- `logs`: per-URL crawl result log.

High-level JS flow:

1. User selects story and URL settings.
2. User clicks `Tao danh sach URL`.
3. JS builds `state.queue` from URL pattern and chapter range.
4. User clicks `Bat dau`.
5. JS calls `es_crawler_start_batch`.
6. Server returns `batch_id` lock.
7. JS starts heartbeat timer.
8. JS processes URLs sequentially with `es_crawler_process_url`.
9. Each response is written into the log table.
10. If 3 process failures happen consecutively, JS stops the batch and calls finalize.
11. When queue finishes, JS calls `es_crawler_finalize_story`.
12. Sidebar shows a short batch summary.
13. Detailed per-URL status remains in the log table.

Finalize summary behavior:

- If all processed URLs are `success`, sidebar shows a simple success summary.
- If there are `duplicate`, `skipped`, or `failed` statuses, sidebar shows a short warning summary and a `Xem log` button.
- The `Xem log` button scrolls to the log card.

## AJAX Actions

Defined in `CrawlerAjax`:

| Constant | Action | Purpose |
| --- | --- | --- |
| `ACTION_START` | `es_crawler_start_batch` | Acquire crawler lock and return `batch_id`. |
| `ACTION_HEARTBEAT` | `es_crawler_heartbeat` | Extend lock TTL while the browser is still running. |
| `ACTION_STOP` | `es_crawler_stop_batch` | Release lock manually. |
| `ACTION_PREVIEW` | `es_crawler_preview_url` | Scrape one URL and return preview only. Does not create a chapter. |
| `ACTION_PROCESS` | `es_crawler_process_url` | Scrape one URL and create a chapter if valid. |
| `ACTION_FINALIZE` | `es_crawler_finalize_story` | Resync story chapter count/latest chapter, clear cache, release lock. |

All actions require:

- Valid nonce: `es_crawler_nonce`.
- Current user can `manage_options`.

## Database Table

`CrawlerLinkTable` manages:

```text
{$wpdb->prefix}es_crawler_links
```

Columns:

| Column | Purpose |
| --- | --- |
| `id` | Primary key. |
| `source_url_hash` | MD5 hash of normalized `clean_url`. |
| `source_url` | Raw source URL entered/generated by crawler. |
| `clean_url` | Normalized source URL used for hashing/fetching. |
| `batch_id` | Batch lock id for grouping crawler rows. |
| `story_id` | Story being crawled into. Required for story-scoped duplicate checks. |
| `chapter_id` | Created Chapter post ID after success. |
| `chapter_number` | Intended chapter number in the selected story. |
| `status` | `pending`, `success`, `failed`, `skipped`, `duplicate`. |
| `error_log` | Error message or duplicate reason. |
| `created_at` | Created timestamp. |
| `updated_at` | Last update timestamp. |

Important indexes:

```sql
PRIMARY KEY (id)
UNIQUE KEY story_source_url_hash (story_id, source_url_hash)
KEY batch_id (batch_id)
KEY story_id (story_id)
KEY chapter_id (chapter_id)
KEY status (status)
KEY chapter_number (chapter_number)
```

Business meaning of unique index:

- Same URL + same story = duplicate.
- Same URL + different story = allowed.

## DB Migration

`CrawlerLinkTable::create()` creates the table and calls `migrate_indexes()`.

Migration behavior:

- Drops old unique index `source_url_hash` if it exists.
- Adds new unique index `story_source_url_hash (story_id, source_url_hash)` if missing.
- Does not drop the table.
- Does not delete existing crawler history.

`Plugin::maybe_run_db_updates()` runs a versioned DB update on plugin boot. It stores the current DB version in:

```text
extend_site_db_version
```

If migration fails and the new index does not exist, the version is not saved, so the next request can retry.

## URL Normalization And Hashing

`CrawlerLinkTable::clean_url_for_hash()` normalizes URLs before hashing:

- Trims whitespace.
- Lowercases scheme and host.
- Preserves user/pass, port, path.
- Removes tracking query params:
  - `fbclid`
  - `gclid`
  - any key starting with `utm_`
- Sorts remaining query params.
- Rebuilds query with RFC3986 encoding.

`hash_url()` returns:

```php
md5($clean_url)
```

Known limitation:

- It does not fully canonicalize all equivalent URLs, for example trailing slash, `www`, `http` vs `https`, or redirects.

## Locking Model

`CrawlerLock` stores one active lock in the WP option:

```text
es_crawler_active_lock
```

Lock payload:

```php
[
    'batch_id' => uuid,
    'user_id' => current_user_id,
    'story_id' => selected story id,
    'expected_total' => generated URL count,
    'started_at' => datetime,
    'last_heartbeat' => datetime,
    'expires_at' => datetime,
]
```

Default TTL is 300 seconds.

Rules:

- Only one active batch can run at a time.
- Expired lock is cleared before acquiring a new one.
- Heartbeat extends `expires_at`.
- Process/finalize requests must match current `batch_id` and `story_id`.

## Preview Flow

`CrawlerAjax::preview_url()`:

1. Verifies request/capability.
2. Reads selected `story_id`.
3. Reads `source_url`.
4. Reads replace rules, title mode, title template, short-content option.
5. Resolves expected chapter number from URL or fallback preview chapter number.
6. Calls `Scraper::scrape()`.
7. Validates scraped chapter number against expected chapter number.
8. Builds final title with `build_chapter_title()`.
9. Returns preview data.

Preview does not:

- Insert Chapter post.
- Insert pending row in `es_crawler_links`.
- Mark duplicate/success/failed tracking.

## Process URL Flow

`CrawlerAjax::process_url()` is the core per-URL import flow.

Step-by-step:

1. Verify nonce and capability.
2. Validate `story_id`.
3. Require lock matching `batch_id` and `story_id`.
4. Validate requested `chapter_number`.
5. Read source URL, title options, post status, cleanup rules.
6. Resolve expected chapter number from source URL or provided number.
7. Clean URL and hash it.
8. Find existing tracking row by `story_id + source_url_hash`.
9. If existing row has `status = success`, verify the linked chapter still exists.
10. If the linked chapter exists, return `duplicate`.
11. If the linked chapter was deleted or moved to trash, treat tracking as stale and allow crawl again.
12. If this is a new tracking row, enforce batch capacity.
13. Insert or reset pending tracking row through `CrawlerLinkTable::insert_pending()`.
14. Check if a Chapter in this story already has the same `_crawler_source_url_hash`.
15. Check if this story already has a Chapter with the same chapter number.
16. Scrape source URL.
17. Validate scraped chapter number.
18. Build final chapter title.
19. Insert Chapter post.
20. Write chapter meta.
21. Mark tracking row as `success` with `chapter_id`.
22. Return success payload.

## Duplicate Rules

There are two duplicate layers.

### 1. Source URL Duplicate In Same Story

Tracking row check:

```php
CrawlerLinkTable::find_by_story_and_hash($story_id, $hash)
```

If a successful tracking row exists, it only blocks when the linked chapter still exists and belongs to the same story.

Chapter existence means:

- Post exists.
- Post type is `ChapterPostType::SLUG`.
- Post status is one of:
  - `publish`
  - `draft`
  - `pending`
  - `private`
  - `future`
- Post meta `ChapterPostType::META_STORY_ID` equals selected `story_id`.

Trash/deleted chapters do not block re-crawling.

### 2. Chapter Number Duplicate In Same Story

Before inserting a chapter, crawler checks:

```php
find_existing_chapter($story_id, $chapter_number)
```

If a live chapter with the same `story_id` and `chapter_number` exists, crawler returns `duplicate` and does not insert another chapter.

## Chapter Meta Written By Crawler

When a chapter is created, crawler writes:

| Meta key | Value |
| --- | --- |
| `ChapterPostType::META_STORY_ID` | Selected story id. |
| `ChapterPostType::META_NUMBER` | Chapter number. |
| `ChapterPostType::META_CHAPTER_VIEWS` | `0` if missing. |
| `_crawler_source_url` | Original source URL. |
| `_crawler_clean_url` | Normalized source URL. |
| `_crawler_source_url_hash` | Hash of clean URL. |

These meta fields are used later for duplicate detection and story/chapter behavior.

## Title Modes

Defined in `CrawlerAjax`:

| Mode | Value | Behavior |
| --- | --- | --- |
| Auto | `auto` | Uses source title unless it is empty, same as story title, or generic. |
| Number only | `number` | `Chương {n}`. |
| Story + number | `story_number` | `{story} - Chương {n}`. |
| Source prefixed | `source_prefixed` | `Chương {n}: {source_title}`. |
| Custom | `custom` | Replaces `{story}`, `{n}`, `{source_title}` in custom template. |

The internal chapter label is produced with byte escapes to avoid mojibake issues:

```php
sprintf("Ch\xC6\xB0\xC6\xA1ng %d", $chapter_number)
```

## Scraper Responsibilities

`Scraper::scrape()`:

1. Cleans and validates source URL.
2. Determines domain.
3. Selects a domain rule or generic rule.
4. Fetches HTML using `wp_remote_get()`.
5. Parses title/content/chapter numbers.
6. Applies replacement cleanup rules.
7. Checks minimum content length unless short content is allowed.
8. Returns structured scrape data or `WP_Error`.

Returned success data includes:

```php
[
    'source_url' => string,
    'clean_url' => string,
    'source_url_hash' => string,
    'domain' => string,
    'rule_label' => string,
    'title' => string,
    'content_html' => string,
    'content_length' => int,
    'source_chapter_number' => int,
    'source_max_chapter_number' => int,
    'warnings' => array,
]
```

## Finalize Flow

`CrawlerAjax::finalize_story()`:

1. Verifies request.
2. Validates story.
3. Checks lock compatibility if lock is active.
4. Calls `ChapterRepository::sync_count_for_story($story_id)`.
5. Calls `LatestChapterTable::resync_story($story_id)`.
6. Clears story cache.
7. Releases lock if `batch_id` was provided.
8. Returns final story metadata.

Frontend does not show detailed publish/draft totals in sidebar anymore. It shows the current batch result summary based on `state.logs`.

## Status Values

Defined in `CrawlerLinkTable`:

| Status | Meaning |
| --- | --- |
| `pending` | URL is queued or being retried. |
| `success` | Chapter was created successfully. |
| `failed` | Scrape, validation, or post insert failed. |
| `skipped` | URL was skipped by logic. Currently reserved for explicit skip cases. |
| `duplicate` | URL/chapter was detected as duplicate and was not inserted. |

## Error And Log Behavior

Server messages in `CrawlerAjax.php` use ASCII text for operational log messages to avoid encoding/mojibake issues in AJAX logs.

UI log behavior:

- Each processed URL gets one log row.
- Status badge uses crawler status.
- Log export is generated from `state.logs`.
- Batch summary is intentionally short.
- Detailed reason is expected to be read from the log table.

## Retry And Auto Stop

JS behavior:

- Failed AJAX processing is retried up to `cfg.max_retries` or 3 by default.
- Retry delay uses `cfg.retry_delay` or 3000 ms.
- If 3 URLs fail consecutively, the batch auto-stops and finalizes.
- Duplicate responses are successful AJAX responses with status `duplicate`, so they do not count as request failures or trigger retry.

## Important Business Decisions

Current crawler business rules:

- Crawler imports chapters into existing stories only.
- Duplicate URL tracking is scoped by `story_id`.
- Duplicate chapter number is scoped by `story_id`.
- Trash/deleted chapters do not block re-crawling.
- Preview does not write tracking rows or chapter posts.
- Only one crawler batch can run at a time across the site.
- Details belong in the log table; sidebar should stay concise.

## Common Change Points

When changing duplicate behavior:

- Update `CrawlerLinkTable` lookup/index logic.
- Update `CrawlerAjax::process_url()` duplicate checks.
- Update chapter meta checks if needed.
- Consider DB migration if indexes change.

When changing title behavior:

- Update `CrawlerAjax::build_chapter_title()`.
- Update `CrawlerAdmin.php` title mode options if UI changes.
- Update `story-crawler.js` preview rendering only if response shape changes.

When changing batch behavior:

- Update `CrawlerLock` if lock policy changes.
- Update `story-crawler.js` queue/heartbeat/finalize logic.
- Update `CrawlerAjax::finalize_story()` if story metadata sync changes.

When changing scraper parsing:

- Update `Scraper.php` rules and parsing helpers.
- Preserve returned payload shape used by preview/process flows.

## Quick Mental Model

```text
Admin page
  -> JS builds queue
  -> start_batch creates lock
  -> process_url for each URL
       -> clean/hash URL
       -> check tracking duplicate inside story
       -> check source hash inside story
       -> check chapter number inside story
       -> scrape source
       -> validate chapter number
       -> insert Chapter post
       -> write crawler meta
       -> mark tracking success/failed/duplicate
  -> finalize_story syncs story count/latest chapter and releases lock
  -> JS shows short summary and detailed log
```
