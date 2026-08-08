# Codex Project Map And Rules

## Project Scope

- Treat `wp-content` as the Git repository root.
- Do not hard-code the local absolute path or drive letter in code, docs, or instructions.
- Custom project code is limited to:
  - `themes/tianshu`
  - `plugins/extend-site`
  - `plugins/extend-referrals`
  - `src/`
- Do not edit WordPress core, third-party plugins/themes, uploads, vendor code, `node_modules`, or built/minified output unless the user explicitly asks.

## Project Map

### Theme

- Active custom theme: `themes/tianshu`
- Theme entry: `themes/tianshu/functions.php`
- Theme includes:
  - `themes/tianshu/includes/theme-setup.php`
  - `themes/tianshu/includes/theme-action.php`
  - `themes/tianshu/includes/theme-filter.php`
  - `themes/tianshu/includes/theme-functions.php`
  - `themes/tianshu/includes/theme-scripts.php`
  - `themes/tianshu/includes/theme-sidebar.php`
- Theme components/templates:
  - `themes/tianshu/template-parts`
  - `themes/tianshu/components`
  - `themes/tianshu/backend`
  - `themes/tianshu/core`
- Theme built assets:
  - `themes/tianshu/assets`

### Extend Site Plugin

- Plugin path: `plugins/extend-site`
- Entry file: `plugins/extend-site/extend-site.php`
- Namespace: `ExtendSite\`
- Text domain: `extend-site`
- Main boot class: `ExtendSite\Core\Plugin`
- Autoloader: `plugins/extend-site/includes/Core/Autoloader.php`
- Main code areas:
  - `includes/Core`
  - `includes/Admin`
  - `includes/Ajax`
  - `includes/Crawler`
  - `includes/DB`
  - `includes/ElementorAddon`
  - `includes/PostType`
  - `includes/Repositories`
  - `includes/Search`
  - `includes/Services`
  - `includes/Views`
  - `includes/Widgets`
- Plugin templates:
  - `plugins/extend-site/templates`
- Admin render views:
  - `plugins/extend-site/includes/Admin/views`
- Crawler admin render views:
  - `plugins/extend-site/includes/Crawler/views`
- Plugin built assets:
  - `plugins/extend-site/assets`

### Extend Referrals Plugin

- Plugin path: `plugins/extend-referrals`
- Entry file: `plugins/extend-referrals/extend-referrals.php`
- Namespace: `ExtendReferrals\`
- Text domain: `extend-referrals`
- Current main code areas:
  - `Admin`
  - `Core`
  - `Repository`
  - `includes/Helper`
  - `views`
  - `assets`
  - `languages`
- Preferred future structure for new code:
  - `includes/Core`
  - `includes/Admin`
  - `includes/DB`
  - `includes/Repository`
  - `includes/Services`
  - `includes/Frontend`
  - `includes/Ajax`
  - `includes/REST`
  - `templates`
- Plugin built assets:
  - `plugins/extend-referrals/assets`

### Source Assets

- Theme source:
  - `src/theme/scss`
  - `src/theme/js`
- Extend Site source:
  - `src/plugins/extend-site/scss`
  - `src/plugins/extend-site/js`
- Extend Referrals source:
  - `src/plugins/extend-referrals/scss`
  - `src/plugins/extend-referrals/js`

## Functional Subsystems

### Theme `tianshu`

- `functions.php` is the theme include manifest.
- `core/` contains theme framework integrations such as TGM plugin activation, theme options, and meta box setup.
- `includes/theme-setup.php` owns theme support, menus, image sizes, and other setup-time features.
- `includes/theme-action.php` owns theme action callbacks and hook registrations.
- `includes/theme-filter.php` owns theme filter callbacks and filter registrations.
- `includes/theme-functions.php` owns general presentation helpers.
- `includes/theme-scripts.php` owns theme frontend asset registration/enqueue.
- `includes/theme-sidebar.php` owns widget area registration.
- `includes/widgets/` owns theme widgets.
- `template-parts/` and `components/` own reusable markup fragments.
- Theme code should render data, not create crawler/search/referral business behavior.

### Extend Site Boot Flow

- `extend-site.php` loads `includes/Core/Autoloader.php` on `plugins_loaded`, registers activation/deactivation hooks, and boots `ExtendSite\Core\Plugin`.
- `ExtendSite\Core\Plugin::boot()` orchestrates the plugin.
- Booted systems include:
  - core enqueue
  - helper/hook files under `functions/` and `hooks/`
  - custom post types
  - admin menu/permalink tools
  - crawler admin/AJAX/template tools
  - Elementor addon
  - frontend/admin AJAX handlers
  - widgets
  - latest chapter DB hooks
  - system job queue and system job AJAX status
  - search controller and shortcode
  - story/chapter admin linking
  - story clone admin action
- Add new boot steps only for real plugin-level subsystems.

### Extend Site Post Types

- Owned by `plugins/extend-site/includes/PostType`.
- Main classes:
  - `BasePostType.php`
  - `StoryPostType.php`
  - `ChapterPostType.php`
  - `AuthorPostType.php`
  - `TemplateLoader.php`
- `StoryPostType` owns the `story` CPT, story taxonomies, story author links, story view/chapter-count meta, and story template map.
- `ChapterPostType` owns chapter content records and chapter-specific behavior.
- `AuthorPostType` owns story author records.
- `TemplateLoader` owns plugin template resolution and theme overrides for plugin-owned post type templates.
- Theme templates may render or override post type templates, but CPT registration and post type business rules stay in `extend-site`.

### Extend Site Crawler

- Owned by `plugins/extend-site/includes/Crawler`.
- Main classes:
  - `CrawlerAdmin.php`
  - `CrawlerAjax.php`
  - `CrawlerLinkTable.php`
  - `CrawlerLock.php`
  - `CrawlerTemplateAdmin.php`
  - `CrawlerTemplateImportExportAdmin.php`
  - `CrawlerTemplateSerializer.php`
  - `CrawlerTemplateTable.php`
  - `CssSelector.php`
  - `Scraper.php`
  - `TemplateQueueBuilder.php`
- `Scraper` fetches source URLs, validates HTML responses, parses chapter title/content, applies replacement rules, and returns normalized scrape data or `WP_Error`.
- `CrawlerLinkTable` owns crawled-source URL normalization/hash/link persistence concerns.
- `CrawlerTemplateTable` owns crawler template storage.
- `CrawlerAdmin`, `CrawlerTemplateAdmin`, and `CrawlerTemplateImportExportAdmin` own crawler admin UI flows.
- Crawler admin pages render through `plugins/extend-site/includes/Crawler/views`; controller classes should prepare data, URLs, callbacks, capability checks, nonce handling, and request handling before loading views.
- `CrawlerAjax` owns crawler AJAX actions.
- Theme must not call crawler internals directly; use plugin admin/AJAX/service entry points.

### Extend Site Crawler Templates

- Owned by `plugins/extend-site/includes/Crawler`.
- Main classes:
  - `CrawlerTemplateAdmin.php`
  - `CrawlerTemplateImportExportAdmin.php`
  - `CrawlerTemplateSerializer.php`
  - `CrawlerTemplateTable.php`
  - `CrawlerAjax.php`
- Crawler templates are stored in the custom table `{$wpdb->prefix}es_crawler_templates`.
- `CrawlerTemplateAdmin` owns the template admin list, search, pagination, trash view, and create/edit form routes.
- `CrawlerTemplateImportExportAdmin` owns template import/export admin routes and download/upload request handling.
- `CrawlerTemplateSerializer` owns the portable JSON payload schema for single-template and collection import/export. When adding/removing crawler template fields, update this serializer so create/edit/import/export stay aligned.
- Import/export design notes and duplicate-handling direction are documented in `.codex/CRAWLER_TEMPLATE_IMPORT_EXPORT_MEMORY.md`; read that note before changing crawler template import behavior.
- `CrawlerTemplateTable` owns crawler template querying, counting, persistence, soft delete, restore, and permanent delete behavior.
- Template deletion uses soft delete via `deleted_at`; default template queries/selects must exclude trashed templates.
- Trashed templates may be restored or permanently deleted only through explicit admin actions with nonce and capability checks.
- Template selects should use AJAX search instead of rendering all templates when the list can grow.
- Crawler execution must only use active/non-trashed templates.

### Extend Site Search

- Owned by `plugins/extend-site/includes/Search`.
- Main classes:
  - `SearchController.php`
  - `SearchRepository.php`
  - `SearchHelper.php`
  - `SearchForm.php`
  - `SearchShortcode.php`
  - `AjaxHandler.php`
- `SearchController` initializes search behavior.
- `SearchRepository` owns search data querying.
- `SearchForm` and `SearchShortcode` own reusable search UI entry points.
- `AjaxHandler` owns frontend search AJAX.
- Theme may render search forms/results, but complex query behavior belongs in this subsystem.

### Extend Site AJAX

- Owned by `plugins/extend-site/includes/Ajax`.
- Booted handlers include:
  - `SearchSelect2`
  - `LoadChapters`
  - `IncrementView`
  - `LoadRanking`
  - `LoadLatestStories`
- AJAX handlers must sanitize request data, verify nonce/capability where appropriate, and return escaped or structured response data.

### Extend Site Data And Tools

- Repositories live in `plugins/extend-site/includes/Repositories`.
- Current repositories include:
  - `StoryRepository`
  - `ChapterRepository`
  - `StoryRankingRepository`
- Repositories own data access and query details.
- Services/tools live in `plugins/extend-site/includes/Services`.
- Current tools include:
  - `Services/Tools/ChapterSyncTool.php`
  - `Services/Tools/SystemJobCleanupTool.php`
  - `Services/Tools/SystemJobRunnerTool.php`
  - `Services/Tools/ToolManager.php`
  - `Services/Tools/ToolInterface.php`
- Current workflow services include:
  - `Services/SystemJobQueue.php`
  - `Services/StoryCloneService.php`
  - `Services/StoryChapterStatusSyncJob.php`
- Services/tools own workflows and business actions; they should call repositories instead of scattering queries through UI code.

### Extend Site System Jobs

- Owned by `plugins/extend-site/includes/Services/SystemJobQueue.php` and `plugins/extend-site/includes/DB/SystemJobTable.php`.
- Jobs are persisted in the custom table `{$wpdb->prefix}es_system_jobs`, not in `wp_options`.
- `SystemJobTable` owns table creation, row insert/update/query, active-job checks, and manual cleanup of finished jobs.
- Job table creation must run through `DBInstaller::install()` and `Plugin::maybe_run_db_updates()` by bumping the Extend Site DB version.
- Current job types include:
  - `clone_story_chapters`: clones chapters from a source story to the newly cloned story.
  - `sync_story_chapter_status`: syncs all chapters of one selected story to `publish` or `draft`.
- Job payloads should store stable IDs. Display labels can be mapped from IDs at render time so renamed stories show current titles.
- Long-running story/chapter work must be chunked through this queue or another explicit batch mechanism, not performed in one admin request.
- The system job UI lives in `Extend Site -> Công cụ`; progress is polled through AJAX and should remain read-only unless a specific tool action is requested.
- Manual cleanup should delete only finished jobs (`done`, `failed`, `cancelled`) and must not delete `pending` or `running` jobs.

### Extend Site Story Clone And Status Sync

- Story clone is owned by `plugins/extend-site/includes/Services/StoryCloneService.php` and `plugins/extend-site/includes/Admin/StoryCloneAdmin.php`.
- The `story` row action `Nhân bản` creates the cloned story immediately as `draft`.
- Chapter cloning is queued as `clone_story_chapters`; chapters are cloned in batches and default to `draft`.
- Cloned chapters must point to the cloned story via `ChapterPostType::META_STORY_ID`.
- Clone workflow should copy content/meta/taxonomies/thumbnail-like references as content data, but reset runtime counters such as story/chapter views and story chapter count.
- Changing a story status must not automatically cascade chapter statuses. Chapter status sync is an explicit tool/job.
- Chapter status sync is owned by `StoryChapterStatusSyncJob` and is initiated from `Extend Site -> Công cụ` after selecting a story and target status.
- Status sync currently supports only `publish` and `draft`; non-publish story statuses map to `draft` when using "follow story status".

### Extend Site Admin, Widgets, And Elementor

- Admin screens live in `plugins/extend-site/includes/Admin`.
- Current admin modules include:
  - `MenuPage`
  - `PermalinkSettings`
  - `StoryCloneAdmin`
  - `StoryChapterLink`
  - `SystemJobAjax`
- Admin render views and small admin partials live in `plugins/extend-site/includes/Admin/views`.
- Widgets live in `plugins/extend-site/includes/Widgets`.
- Elementor addon code lives in `plugins/extend-site/includes/ElementorAddon`.
- Admin modules must keep nonce and capability checks close to the request handling code.
- Admin page controllers should prepare view data and handle POST/AJAX intent; views should only render escaped output from passed data.
- The system tools page is rendered by `includes/Admin/views/tools.php` and may enqueue page-specific admin JS from `assets/js/backend/system-jobs.js`.

### Extend Referrals Boot Flow

- `extend-referrals.php` defines plugin constants, registers the `ExtendReferrals\` autoloader, loads the text domain, and boots `ExtendReferrals\Core\Plugin` on `plugins_loaded`.
- `ExtendReferrals\Core\Plugin::init()` boots:
  - admin menu on `init`
  - admin assets on `admin_enqueue_scripts`
  - frontend assets on singular pages allowed by display rules
  - content injection through `AdsManager::inject_ads_into_content`
- Current code uses top-level namespace folders (`Admin`, `Core`, `Repository`) instead of the preferred future `includes/*` layout. Do not move files only for style unless the user asks for a refactor.

### Extend Referrals Ads, Partners, And Display Rules

- Current plugin behavior is ads/partner placement with TTL and display rules.
- Owned by `plugins/extend-referrals`.
- Main classes:
  - `Core/AdsManager.php`
  - `Core/DisplayRules.php`
  - `Core/TTLManager.php`
  - `Core/Shortcode.php`
  - `Core/Helpers.php`
  - `Repository/AdsCache.php`
- `AdsManager` owns frontend ad/partner injection into content.
- `DisplayRules` owns rules that decide where ads/partners can appear.
- `TTLManager` owns expiry/rotation timing behavior.
- `Shortcode` owns shortcode-based rendering entry points.
- `AdsCache` owns cached ads/partner data access.
- `includes/Helper/ImageHelper.php` owns image helper behavior.
- Keep ads/partner/referral display logic out of the theme and out of `extend-site`.

### Extend Referrals Admin And Views

- Admin classes live in `plugins/extend-referrals/Admin`.
- Current admin classes include:
  - `AdminMenu.php`
  - `Pages/AdsSettingsPage.php`
  - `Pages/AdvancedRulesPage.php`
  - `Pages/DisplayRulesPage.php`
- Backend views live in `plugins/extend-referrals/views/backend`.
- Frontend views live in `plugins/extend-referrals/views/frontend`.
- Current views include:
  - `backend/partner-page.php`
  - `backend/partials/partner-item.php`
  - `backend/display-rules-page.php`
  - `backend/advanced-rules-page.php`
  - `frontend/partner-info.php`
- Views should be thin render files. Sanitize before persistence and escape at output.

### Extend Referrals Future Referral Features

- If true referral tracking is added later, keep it in `extend-referrals`.
- Suggested future modules:
  - referral code/link generation
  - cookie/session attribution
  - click tracking
  - conversion tracking
  - referral reports
  - referral-specific DB tables
  - public helper/template tags for theme rendering
- Future referral conversion writes must be idempotent and must avoid duplicate conversion records.

## Ownership Boundaries

### `themes/tianshu`

- Owns presentation only: templates, markup, theme hooks/filters, widgets, and theme assets.
- May read plugin data through public helpers, template tags, shortcodes, or service APIs.
- Must not own referral, crawler, search, CPT, or database business logic.
- Must not fatal when `extend-site` or `extend-referrals` is inactive; always guard plugin-dependent calls.

### `plugins/extend-site`

- Owns general site extensions and shared platform behavior.
- Keep CPTs, taxonomies, template loading, search, crawler, Elementor widgets, general admin modules, shared DB installers, and shared services here.
- Do not put referral-specific TTL, attribution, commission, click, or conversion logic here unless it is a small shared integration point.

### `plugins/extend-referrals`

- Owns referral-specific business logic.
- Keep referral code/link generation, cookies, TTL, attribution, click tracking, conversion tracking, referral reports, and referral-specific DB tables here.
- Do not put story/chapter/search/crawler/site-core logic here.
- Expose safe public helpers or services for the theme to consume.

## Architecture Rules

- Follow the existing project style before introducing a new pattern.
- Keep changes narrow and scoped to the relevant theme/plugin.
- Prefer classes and services for plugin business logic.
- Keep bootstrap files thin: define constants, load autoloader, boot the plugin.
- For admin pages, prefer controller/view separation: controller classes handle request data, capabilities, nonces, redirects, queries, URLs, and service calls; view files render markup only.
- Do not put persistence, request handling, DB queries, or long-running workflow logic inside admin view files.
- Included PHP view files do not declare the caller class namespace. Avoid unqualified namespaced class references inside views; pass URLs, data, labels, and callbacks through view data instead.
- Use each plugin's namespace consistently:
  - `ExtendSite\`
  - `ExtendReferrals\`
- New class file paths should match their namespace.
- Do not add broad refactors unless the user approves.
- Do not move business logic between theme and plugins without explicit approval.
- Do not restructure `extend-referrals` into the preferred future `includes/*` layout unless the user explicitly asks for that refactor.
- Do not perform "cleanup" refactors while fixing unrelated behavior.
- Some existing files contain broken Vietnamese encoding. Do not fix file-wide encoding or rewrite Vietnamese strings unless the task is specifically about that text or the touched lines require it.

## WordPress Rules

- Escape output at render time:
  - `esc_html()`
  - `esc_attr()`
  - `esc_url()`
  - `wp_kses_post()`
- Sanitize all input:
  - `sanitize_text_field()`
  - `sanitize_key()`
  - `sanitize_email()`
  - `absint()`
  - `wp_unslash()` before sanitizing request values when needed.
- Use nonces and capability checks for admin actions, AJAX, REST, forms, and privileged operations.
- Use prepared queries for custom SQL.
- Do not echo raw user input, request data, post meta, term meta, options, or database values.
- Do not call `flush_rewrite_rules()` in normal runtime code.
- Database schema changes must go through installer/update routines and version options. Do not run ad hoc schema changes during normal runtime.
- Keep custom table creation/update logic in the owning plugin's DB/installer subsystem.
- Use the correct text domain:
  - Theme strings: `tianshu`
  - Extend Site strings: `extend-site`
  - Extend Referrals strings: `extend-referrals`

## Crawler Safety Rules

- Crawler work must stay inside `plugins/extend-site/includes/Crawler` unless the user approves a broader architecture change.
- Always validate source URLs before fetching.
- Always use finite HTTP timeout and redirect limits.
- Always handle `WP_Error` responses.
- Avoid duplicate crawl work through the existing URL hash, link table, template table, or lock mechanisms.
- Do not add crawler code that can loop through unbounded URLs, posts, chapters, or domains in a single request.
- Long-running crawl/sync work should be chunked, locked, paginated, or AJAX/cron-driven.
- Do not let theme templates trigger crawler fetches.

## Referral Rules

- Referral attribution must be deterministic and idempotent.
- Conversion recording must avoid duplicates for the same source event/order/user.
- Cookie names, TTL, and referral query keys should be centralized in config/constants.
- All referral request values must be sanitized before use.
- Admin reports must enforce capability checks.
- AJAX/REST referral endpoints must verify nonce or use an intentional public-read design with strict sanitization and rate-conscious behavior.
- Referral templates/views must escape all dynamic values.

## Theme Integration Rules

- Theme-to-plugin calls must be guarded with `function_exists()`, `class_exists()`, or safe wrapper helpers.
- Prefer small theme wrapper helpers when templates need plugin data.
- Keep raw SQL and direct repository access out of theme templates.
- Keep output escaping in templates, even when data comes from plugin services.

## Asset Rules

- Edit source assets under `src/` first.
- Do not edit built files under `assets/` when source files exist unless the user explicitly asks for output-only changes.
- Frontend stack is SCSS plus jQuery/plain JavaScript.
- Do not add a frontend framework or major dependency unless the user approves.
- Keep enqueues in the owning package:
  - Theme enqueues in `themes/tianshu/includes/theme-scripts.php`
  - Extend Site enqueues in its plugin enqueue system.
  - Extend Referrals enqueues in its plugin enqueue system.

## Search And Scan Rules

- Avoid heavy full-repository scans.
- Prefer targeted reads and searches scoped to:
  - `themes/tianshu`
  - `plugins/extend-site`
  - `plugins/extend-referrals`
  - `src`
- Do not recursively scan `node_modules`, `uploads`, vendor directories, third-party plugins/themes, built assets, or framework core unless the user approves.
- Use `rg`/targeted searches when available.
- Start exploration with directory listings and key entry files before reading many files.
- Prefer exact filenames, class names, function names, hooks, handles, or text patterns over broad scans.
- Keep recursive searches bounded to the smallest relevant directory.
- Do not run broad commands such as scanning all of `wp-content`, all plugins, all themes, or all PHP files unless the user approves.
- Do not recurse into:
  - `wp-admin`
  - `wp-includes`
  - `uploads`
  - `node_modules`
  - `vendor`
  - third-party plugin/theme folders
  - built asset folders such as `assets`
  - framework/library folders such as Carbon Fields, Codestar, CMB2, Elementor, or TGMPA
- If a search may be large, ask the user before running it and explain the intended scope.
- If a command appears slow or too broad, stop and narrow the scope instead of waiting indefinitely.
- Prefer reading only the top part of large files first, then continue only if needed.

## Workflow Rules

- Reply to the user in Vietnamese.
- Present a short plan before editing files.
- Ask before changing unclear architecture or doing broad/risky refactors.
- After edits, summarize:
  - Files changed
  - Why each change was made
  - Any verification performed or skipped
- Do not commit changes unless the user explicitly asks.
- Do not revert user changes unless the user explicitly asks for that exact operation.

## Practical Decision Guide

- If the change affects page markup, layout, template parts, or theme-only hooks, use `themes/tianshu`.
- If the change affects stories, chapters, authors, site search, crawler, Elementor, shared admin options, or general site infrastructure, use `plugins/extend-site`.
- If the change affects referral links, referral cookies, TTL, attribution, click logs, conversions, reports, or referral admin settings, use `plugins/extend-referrals`.
- If the change affects CSS/JS, edit the matching source folder under `src/` first.
