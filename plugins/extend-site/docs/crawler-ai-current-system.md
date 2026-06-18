# Extend Site Crawler - Current System Context For AI

Tai lieu nay mo ta he thong crawler hien tai cua plugin WordPress `extend-site`, dua tren 4 prompt trien khai va code thuc te dang co trong repository.

Muc tieu cua file nay la giup AI/developer doc nhanh kien truc hien tai truoc khi sua tiep crawler, tranh nham lan voi yeu cau ban dau trong prompt.

## 1. Tong Quan

Crawler la mot module ben trong plugin `wp-content/plugins/extend-site`, khong phai plugin rieng.

Module nay dung de crawl noi dung chuong tu URL ben ngoai va chen vao mot Story da ton tai.

Crawler hien tai:

- Chi tao `chapter`, khong tao `story` hay `story_author`.
- Khong dung `post_parent` de lien ket chapter voi story.
- Lien ket chapter voi story bang post meta `_chapter_story_id`.
- Luu so chuong bang post meta `_chapter_number`.
- Xu ly tung URL mot qua AJAX.
- JS admin page la thanh phan dieu phoi batch/queue.
- Backend giu lock de dam bao moi thoi diem chi co mot batch crawler active.
- Sau batch, finalize se dong bo lai `_chapter_count`, latest chapter table va cache lien quan.

## 2. Cac File Chinh

| File | Vai tro |
| --- | --- |
| `extend-site.php` | Entry point plugin, dang ky autoloader va activation hook. |
| `includes/Core/Plugin.php` | Boot cac module, trong do co crawler admin/AJAX va migration DB crawler. |
| `includes/Core/Enqueue.php` | Enqueue JS/CSS crawler chi tren trang admin crawler. |
| `includes/DB/DBInstaller.php` | Tao cac bang DB khi active plugin, gom ca bang crawler. |
| `includes/Crawler/CrawlerAdmin.php` | Tao submenu va render admin UI crawler. |
| `includes/Crawler/CrawlerAjax.php` | Controller AJAX: start, heartbeat, stop, preview, process URL, finalize. |
| `includes/Crawler/CrawlerLinkTable.php` | Wrapper bang DB `es_crawler_links`. |
| `includes/Crawler/CrawlerLock.php` | Runtime lock bang WordPress option. |
| `includes/Crawler/Scraper.php` | Fetch HTML, parse DOM/XPath, clean content, detect chapter, validate length. |
| `assets/js/backend/story-crawler.js` | Dieu phoi frontend queue, retry, pause/resume, heartbeat, log, finalize. |
| `assets/css/backend/story-crawler.css` | Style cho admin crawler page. |
| `includes/PostType/ChapterPostType.php` | Dinh nghia CPT chapter va hooks sync `_chapter_count`. |
| `includes/DB/LatestChapterTable.php` | Bang phu latest chapter va ham `resync_story()`. |
| `includes/Repositories/ChapterRepository.php` | Ham sync/count/query chapter theo story. |

## 3. Bootstrap Va Activation

Plugin boot tai `includes/Core/Plugin.php`.

Luồng chính:

1. `extend-site.php` load autoloader trong hook `plugins_loaded`.
2. `new Plugin()->boot()` duoc goi.
3. `Plugin::boot()` goi:
   - `maybe_run_db_updates()`
   - `active_custom_post_types()`
   - `active_menu_page_admin()`
   - `active_crawler()`
   - cac module AJAX/widget/search khac.
4. `active_crawler()` goi:
   - `CrawlerAdmin::init()`
   - `CrawlerAjax::init()`

Khi active plugin, `DBInstaller::install()` tao cac bang:

- `LatestChapterTable::create()`
- `ViewsStoryDailyTable::create()`
- `CrawlerLinkTable::create()`

Ngoai activation, `Plugin::maybe_run_db_updates()` cung goi `CrawlerLinkTable::create()` neu `extend_site_db_version` chua dung version hien tai.

DB version hien tai:

```php
private const DB_VERSION = '20260608_crawler_link_story_hash';
```

## 4. Data Model

Story CPT:

```php
ExtendSite\PostType\StoryPostType::SLUG // story
```

Chapter CPT:

```php
ExtendSite\PostType\ChapterPostType::SLUG // chapter
```

Chapter lien ket voi Story bang:

```php
ExtendSite\PostType\ChapterPostType::META_STORY_ID // _chapter_story_id
```

So chuong:

```php
ExtendSite\PostType\ChapterPostType::META_NUMBER // _chapter_number
```

View count chapter:

```php
ExtendSite\PostType\ChapterPostType::META_CHAPTER_VIEWS // _chapter_view_count
```

Tong so chapter cua story:

```php
ExtendSite\PostType\StoryPostType::META_CHAPTER_COUNT // _chapter_count
```

Quy tac quan trong:

- Tuyet doi khong dung `post_parent` cho chapter.
- Khong insert truc tiep vao `wp_posts` hoac `wp_postmeta` khi tao chapter.
- Crawler tao chapter bang `wp_insert_post()` va post meta APIs.

## 5. Bang Tracking Crawler

Bang crawler:

```text
{$wpdb->prefix}es_crawler_links
```

Duoc quan ly boi `CrawlerLinkTable`.

Schema hien tai:

| Column | Mo ta |
| --- | --- |
| `id` | Primary key. |
| `source_url_hash` | MD5 cua URL da clean. |
| `source_url` | URL goc frontend gui len. |
| `clean_url` | URL da clean dung de hash/fetch. |
| `batch_id` | ID batch hien tai. |
| `story_id` | Story dang crawl vao. |
| `chapter_id` | Chapter da tao neu thanh cong. |
| `chapter_number` | So chuong du kien. |
| `status` | `pending`, `success`, `failed`, `skipped`, `duplicate`. |
| `error_log` | Loi hoac ly do skip/duplicate. |
| `created_at` | Thoi diem tao row. |
| `updated_at` | Thoi diem cap nhat row. |

Index quan trong:

```sql
UNIQUE KEY story_source_url_hash (story_id, source_url_hash)
```

Khac voi prompt ban dau: code thuc te khong unique global theo `source_url_hash`; no unique theo cap `(story_id, source_url_hash)`.

Y nghia:

- Cung URL + cung story => duplicate.
- Cung URL + story khac => duoc phep crawl.

`CrawlerLinkTable::create()` co migration:

- Drop index cu `source_url_hash` neu ton tai.
- Tao index moi `story_source_url_hash`.
- Khong drop table, khong xoa history.

## 6. Clean URL Va Hash

`CrawlerLinkTable::clean_url_for_hash()` lam viec:

- Trim URL.
- Lowercase scheme va host.
- Giu user/pass, port, path.
- Xoa query tracking:
  - `fbclid`
  - `gclid`
  - cac param bat dau bang `utm_`
- Sort query con lai.
- Build query theo RFC3986.

Hash:

```php
md5($clean_url)
```

Luu y:

- Ham nay chua canonicalize tat ca bien the URL nhu trailing slash, `www`, redirect, http/https.
- Duplicate logic dua tren URL sau clean, khong dua tren final redirected URL.

## 7. Runtime Lock

`CrawlerLock` luu mot lock duy nhat trong option:

```text
es_crawler_active_lock
```

Payload:

```php
[
    'batch_id' => uuid,
    'user_id' => current user,
    'story_id' => selected story,
    'expected_total' => number of URLs,
    'started_at' => datetime,
    'last_heartbeat' => datetime,
    'expires_at' => datetime,
]
```

TTL default:

```php
CrawlerLock::DEFAULT_TTL // 300 seconds
```

Quy tac:

- `start_batch` tu choi neu co lock chua expire.
- Neu lock expire thi clear truoc khi acquire lock moi.
- `heartbeat` chi refresh lock neu `batch_id` khop.
- `process_url` yeu cau lock khop `batch_id` va `story_id`.
- `stop_batch` chi release neu `batch_id` khop.
- `finalize_story` chi release lock neu `batch_id` khop.

## 8. Admin UI

`CrawlerAdmin` tao submenu:

```php
parent slug: extend-site-main
page slug: extend-site-crawler
capability: manage_options
```

UI gom:

- Story selector bang Select2.
- URL pattern voi placeholder `{n}`.
- Range `from` / `to`.
- Padding: none, 2 digit, 3 digit.
- Preview chapter number.
- Optional preview URL override.
- Post status: `publish` hoac `draft`.
- Title mode.
- Delay giua moi URL.
- Find/replace cleanup rules.
- Option xoa ca container chua text can tim.
- Buttons: preview, generate URLs, start, pause/resume, stop, finalize again.
- Progress bar.
- URL generated list.
- Preview panel.
- Log table va export textarea.

Luu y thuc te:

- Default post status trong UI hien tai la `publish`, du prompt noi co the dung `draft` an toan hon.
- UI text trong mot so file PHP hien dang bi mojibake encoding, nhung JS log hien thi tieng Viet UTF-8 tot hon.

## 9. Enqueue Asset

`Enqueue::enqueue_scripts_backend()` chi load crawler assets khi:

```php
$screen->id === 'extend-site_page_' . CrawlerAdmin::PAGE_SLUG
```

Assets:

- CSS: `assets/css/backend/story-crawler.css`
- JS: `assets/js/backend/story-crawler.js`
- Dependency: `jquery`, `select2`

Localized object:

```js
window.esStoryCrawler
```

Gia tri chinh:

- `ajax_url`
- `nonce`
- `story_search_nonce`
- `story_search_action`
- `process_action`
- `preview_action`
- `start_batch_action`
- `heartbeat_action`
- `stop_batch_action`
- `finalize_action`
- `default_delay`
- `retry_delay`
- `max_retries`
- `max_batch_size`
- `heartbeat_interval`

## 10. JavaScript Batch Flow

File:

```text
assets/js/backend/story-crawler.js
```

State chinh:

```js
state = {
    queue: [],
    index: 0,
    processed: 0,
    isRunning: false,
    isPaused: false,
    consecutiveFailures: 0,
    batchId: '',
    storyId: 0,
    heartbeatTimer: null,
    lastFinalizePayload: null,
    logs: []
}
```

Luồng chạy:

1. User chon story, URL pattern, range.
2. Click `Tao danh sach URL`.
3. JS validate:
   - story da chon.
   - URL pattern co `{n}`.
   - from/to la positive integer.
   - `to >= from`.
   - URL build ra la http/https.
   - count khong vuot `max_batch_size`.
4. JS tao queue tu range.
5. Click `Bat dau`.
6. JS goi `es_crawler_start_batch` voi `story_id` va `expected_total`.
7. Backend tra `batch_id`.
8. JS start heartbeat moi 30s.
9. JS xu ly tung URL tuan tu bang `es_crawler_process_url`.
10. Moi URL thanh cong/duplicate/skipped/failed duoc log.
11. Neu AJAX fail, JS retry toi da 3 lan.
12. Neu 3 URL fail lien tiep, JS tu dung batch va finalize.
13. Khi queue xong, JS goi `es_crawler_finalize_story`.

Pause/resume:

- Pause chi dung viec bat dau URL tiep theo.
- Request dang chay khong bi cancel.
- Heartbeat van tiep tuc khi pause.

Stop:

- Set running false.
- Goi `es_crawler_stop_batch`.
- Sau do code hien tai van goi `finalizeBatch()`.
- Vi stop da release lock va `state.batchId` bi clear truoc finalize, finalize co the chay voi `batch_id` rong.

## 11. AJAX Actions

Tat ca action nam trong `CrawlerAjax`.

Tat ca action goi `verify_request()`:

- `check_ajax_referer(CrawlerAjax::NONCE_ACTION, 'nonce')`
- user phai co capability `manage_options`

Actions:

| Action | Method | Mo ta |
| --- | --- | --- |
| `es_crawler_start_batch` | `start_batch()` | Validate story + expected_total, acquire lock. |
| `es_crawler_heartbeat` | `heartbeat()` | Refresh lock theo batch_id. |
| `es_crawler_stop_batch` | `stop_batch()` | Release lock theo batch_id. |
| `es_crawler_preview_url` | `preview_url()` | Scrape 1 URL, khong insert DB/post. |
| `es_crawler_process_url` | `process_url()` | Scrape va insert 1 chapter neu hop le. |
| `es_crawler_finalize_story` | `finalize_story()` | Sync count/latest/cache va release lock neu khop. |

## 12. Preview Flow

`CrawlerAjax::preview_url()`:

1. Verify nonce/capability.
2. Validate `story_id`.
3. Validate `chapter_number`.
4. Lay `source_url`.
5. Lay replace rules, allow short content, title mode/template.
6. Resolve expected chapter number tu URL neu URL co so chuong; fallback la chapter number user gui len.
7. Goi `Scraper::scrape()`.
8. Validate source chapter number voi expected chapter number.
9. Build final title.
10. Return preview data.

Preview khong:

- Tao chapter.
- Insert/update tracking row.
- Tao lock.
- Update `_chapter_count`.
- Update latest chapter table.
- Clear cache.

## 13. Process URL Flow

`CrawlerAjax::process_url()` xu ly mot URL.

Luồng chi tiet:

1. Verify request.
2. Validate story.
3. Kiem tra lock khop `batch_id` + `story_id`.
4. Validate chapter number.
5. Lay source URL, post status, replace rules, title options.
6. Resolve expected chapter number tu URL.
7. Clean URL va hash URL.
8. Tim tracking row theo `(story_id, hash)`.
9. Neu tracking row `success` va chapter lien ket van ton tai trong story, return `duplicate`.
10. Neu tracking success cu nhung chapter da mat/khong con thuoc story, coi la stale va cho crawl lai.
11. Neu tracking moi, enforce batch capacity dua tren `expected_total`.
12. Insert/reset pending row.
13. Check chapter co cung `_crawler_source_url_hash` trong story.
14. Check chapter co cung `_chapter_number` trong story.
15. Goi `Scraper::scrape()`.
16. Validate source chapter number.
17. Build final chapter title.
18. `wp_insert_post()` voi `post_type = chapter`.
19. Write meta crawler va chapter meta.
20. `CrawlerLinkTable::mark_success($tracking_id, $chapter_id)`.
21. Return JSON success.

Neu loi fetch/parse/validate/insert:

- Tracking row duoc mark `failed`.
- Response JSON error tra ve payload phu hop de JS retry/log.

Neu duplicate:

- Tracking row duoc mark `duplicate`.
- Khong tao chapter moi.
- Response la JSON success voi `status = duplicate`.

## 14. Duplicate Protection

Crawler co nhieu lop duplicate guard.

### 14.1 Duplicate URL Theo Story

Kiem tra tracking:

```php
CrawlerLinkTable::find_by_story_and_hash($story_id, $hash)
```

Neu status `success` va chapter lien ket con hop le, crawler tra duplicate.

Chapter hop le khi:

- Post ton tai.
- Post type la `chapter`.
- Post status trong `publish`, `draft`, `pending`, `private`, `future`.
- Meta `_chapter_story_id` bang selected story.

Trash/deleted chapter khong block crawl lai.

### 14.2 Duplicate Source Hash Trong Chapter Meta

Truoc insert, crawler tim chapter co:

- `_chapter_story_id = story_id`
- `_crawler_source_url_hash = hash`

Neu co, mark duplicate.

### 14.3 Duplicate Chapter Number

Truoc insert, crawler tim chapter co:

- `_chapter_story_id = story_id`
- `_chapter_number = chapter_number`

Neu co, mark duplicate.

Day la guard quan trong nhat de tranh tao 2 chuong cung so trong mot story.

## 15. Chapter Insert

Crawler tao post bang:

```php
wp_insert_post([...], true)
```

Input chinh:

- `post_type`: `ChapterPostType::SLUG`
- `post_title`: title da build server-side.
- `post_content`: HTML da scrape/clean.
- `post_status`: `publish` hoac `draft`.

Meta duoc ghi:

| Meta key | Gia tri |
| --- | --- |
| `_chapter_story_id` | Story ID. |
| `_chapter_number` | So chuong. |
| `_chapter_view_count` | 0 neu missing. |
| `_crawler_source_url` | Source URL goc. |
| `_crawler_clean_url` | URL da clean. |
| `_crawler_source_url_hash` | MD5 cua clean URL. |

Sau `wp_insert_post()`, code van `update_post_meta()` lai cac meta tren de dam bao meta duoc ghi.

## 16. Title Mode

Server build title trong `CrawlerAjax::build_chapter_title()`.

Modes:

| Mode | Gia tri | Ket qua |
| --- | --- | --- |
| Auto | `auto` | Dung source title neu hop ly, fallback `Chuong {n}`. |
| Number | `number` | Chi dung `Chuong {n}`. |
| Story number | `story_number` | `{story} - Chuong {n}`. |
| Source prefixed | `source_prefixed` | `Chuong {n}: {source_title}`. |
| Custom | `custom` | Replace `{story}`, `{n}`, `{source_title}` trong template. |

UI hien tai default mode la `number`.

## 17. Scraper

File:

```text
includes/Crawler/Scraper.php
```

`Scraper::scrape($source_url, $replace_rules, $allow_short_content)`:

1. Clean URL va validate bang `wp_http_validate_url()`.
2. Lay domain.
3. Chon rule parse.
4. Fetch HTML bang `wp_remote_get()`.
5. Parse DOMDocument/DOMXPath.
6. Remove unwanted nodes.
7. Chon title/content.
8. Apply replacement rules.
9. Sanitize HTML bang `wp_kses_post()`.
10. Cleanup empty/unwanted fragments.
11. Detect trang loi/captcha/blocked.
12. Validate min content length.
13. Return data hoac `WP_Error`.

HTTP config:

- `timeout`: default 30s, filter `es_crawler_http_timeout`.
- `connecttimeout`: default 10s, filter `es_crawler_http_connect_timeout`.
- `redirection`: 5.
- User-Agent qua filter `es_crawler_user_agent`.

Content minimum:

```php
Scraper::DEFAULT_MIN_CONTENT_LENGTH // 300
```

Co filter:

```php
es_crawler_min_content_length
```

Domain rules:

- Prompt ban dau yeu cau rule map cho domain nhu `tinhvan.site`, `doctruyenchill.net`.
- Code thuc te hien tai `Scraper::get_rules()` tra ve `apply_filters('es_crawler_domain_rules', [])`.
- Domain-specific rules chi duoc dung neu filter `es_crawler_use_domain_rules` tra `true`.
- Neu khong co rule, scraper dung generic rule heuristic.

Viec nay nghia la hien tai crawler mac dinh khong co hardcoded domain rules.

## 18. Replace Rules

JS tao replace rules tu 2 textarea `find` va `replace`.

Dang payload:

```json
[
  {
    "find": "text can tim",
    "replace": "text thay the",
    "regex": false,
    "remove_container": true
  }
]
```

Backend:

- Chap nhan `replace_rules` dang JSON string hoac array.
- Rule co `regex = true` co the chay `preg_replace()`, nhung UI hien tai khong expose regex.
- `remove_container = true` voi plain text se xoa block/container chua text do trong DOM fragment sau sanitize.

## 19. Chapter Number Validation Tu Source

Crawler co logic tranh site nguon fallback ve chuong khac khi URL khong ton tai.

Backend resolve expected chapter:

```php
resolve_expected_chapter_number($source_url, $fallback_chapter_number)
```

No uu tien so chuong detect tu URL:

- Query params: `chuong`, `chapter`, `chap`, `tap`.
- Path pattern: `chuong-123`, `chapter/123`, `chap_123`, `tap123`.

Scraper cung detect source chapter number/max chapter number tu DOM:

- Input current chapter.
- Active/current labels.
- Links cung path/query de tim max chapter.

Sau scrape, `validate_scraped_chapter_number()`:

- Neu source max > 0 va expected > max => fail.
- Neu source chapter number > 0 va khac expected => fail.
- Neu khong detect duoc source chapter number => cho qua.

## 20. Finalize

`CrawlerAjax::finalize_story()`:

1. Verify request.
2. Validate story.
3. Neu co lock active:
   - batch_id phai khop.
   - lock story_id phai khop.
4. Goi:

```php
ChapterRepository::sync_count_for_story($story_id)
LatestChapterTable::resync_story($story_id)
```

5. Clear cache:

```php
wp_cache_delete("es:story_last_update:{$story_id}", 'es_story')
delete_transient("es:story_last_update:{$story_id}")
```

6. Release lock neu co `batch_id`.
7. Return:
   - `chapter_count`
   - `chapter_status_counts`
   - `latest_chapter`
   - `lock_released`

Finalize duoc thiet ke idempotent: goi lai nhieu lan se resync lai count/latest, khong tao chapter.

## 21. Derived Data Va Hooks

### 21.1 `_chapter_count`

`ChapterRepository::sync_count_for_story($story_id)` dem published chapters theo:

- post type `chapter`
- post status `publish`
- meta `_chapter_story_id = story_id`

Sau do update:

```php
_chapter_count
```

Hooks trong `ChapterPostType` cung goi sync khi:

- `save_post_chapter`
- `transition_post_status`
- `before_delete_post` / `deleted_post`
- `_chapter_story_id` meta added/updated/deleted

Crawler insert chapter bang WordPress API nen cac hooks nay co the chay. Finalize van sync lai de dam bao ket qua cuoi dung.

### 21.2 Latest Chapter Table

`LatestChapterTable` quan ly:

```text
{$wpdb->prefix}es_story_latest_chapter
```

Hooks:

- `save_post_chapter`
- `transition_post_status`
- `before_delete_post`
- `updated_post_meta`

Finalize crawler goi:

```php
LatestChapterTable::resync_story($story_id)
```

Ham nay chon published chapter co `_chapter_number` cao nhat trong story.

Luu y: logic hook `update_on_save_post()` dua vao `post_date/post_modified`, con `resync_story()` dua vao `_chapter_number` cao nhat. Sau batch crawler, finalize la nguon dang tin hon cho latest chapter.

### 21.3 Story Last Update Cache

Cache key:

```text
es:story_last_update:{story_id}
```

Hooks trong `hooks/cpt-hooks.php` clear cache khi:

- save chapter.
- delete chapter.

Finalize crawler clear lai cache mot lan nua de dam bao an toan.

## 22. Gioi Han Batch

Frontend enforce:

```js
cfg.max_batch_size || 200
```

Backend enforce trong `start_batch()`:

```php
CrawlerAjax::MAX_BATCH_SIZE // 200
```

Co filter:

```php
es_crawler_max_batch_size
```

Backend yeu cau `expected_total > 0`.

`process_url()` con goi `enforce_batch_capacity($batch_id)` khi tao tracking row moi.

## 23. Status Va Response

Status tracking:

- `pending`
- `success`
- `failed`
- `skipped`
- `duplicate`

Process response payload base:

```php
[
    'status' => string,
    'message' => string,
    'source_url' => string,
    'clean_url' => string,
    'story_id' => int,
    'chapter_id' => int,
    'chapter_number' => int,
    'content_length' => int,
    'source_chapter_number' => int,
    'source_max_chapter_number' => int,
    'warnings' => array,
]
```

Duplicate response la JSON success voi `status = duplicate`, nen JS xem nhu URL da xu ly xong, khong retry.

Failed response la JSON error, JS retry toi da 3 lan.

## 24. Cac Diem Khac Prompt Ban Dau

Nhung diem code thuc te khac hoac da mo rong so voi prompt:

- Unique URL hash dang scoped theo story, khong global.
- Scraper mac dinh dung generic heuristic; domain-specific rules khong hardcoded.
- UI default post status la `publish`.
- Co them title mode va title template.
- Co them custom delay giua requests.
- JS tu dung neu 3 URL fail lien tiep.
- Backend start batch bat buoc co `expected_total`.
- Latest chapter finalize chon chapter published co `_chapter_number` cao nhat.
- Mot so string trong PHP admin UI dang mojibake, can can than khi sua text/encoding.

## 25. Mental Model Ngan Gon

```text
Admin crawler page
  -> JS tao queue tu URL pattern + range
  -> start_batch tao lock voi expected_total
  -> heartbeat giu lock song
  -> moi URL goi process_url tuan tu
       -> validate lock/story/chapter number
       -> clean + hash URL
       -> check duplicate tracking trong story
       -> insert/reset pending row
       -> check duplicate source hash trong chapter meta
       -> check duplicate chapter number trong story
       -> scrape HTML
       -> validate source chapter number
       -> insert chapter bang wp_insert_post()
       -> write meta
       -> mark tracking success/failed/duplicate
  -> finalize_story
       -> sync _chapter_count
       -> resync latest chapter table
       -> clear story cache
       -> release lock neu batch_id khop
  -> JS hien summary ngan + log chi tiet
```

## 26. Nguyen Tac Khi Sua Tiep

Khi AI/developer sua module nay:

- Khong tao plugin rieng.
- Khong dung `post_parent`.
- Khong insert chapter bang SQL truc tiep.
- Khong bo qua nonce/capability.
- Khong tin frontend-only validation.
- Preview khong duoc tao side effect DB/post/lock.
- Process moi request chi xu ly 1 URL.
- Neu doi duplicate logic, phai xem ca:
  - `CrawlerLinkTable`
  - `CrawlerAjax::process_url()`
  - chapter crawler meta
  - DB indexes/migration
- Neu doi scraper response, phai update ca preview/process JS render.
- Neu doi count/latest behavior, phai xem:
  - `ChapterRepository::sync_count_for_story()`
  - `LatestChapterTable::resync_story()`
  - hooks trong `ChapterPostType`
  - `CrawlerAjax::finalize_story()`

