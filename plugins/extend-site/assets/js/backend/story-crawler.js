(function ($) {
    'use strict';

    const cfg = window.esStoryCrawler || {};
    const state = {
        queue: [],
        index: 0,
        processed: 0,
        isRunning: false,
        isPaused: false,
        previewNumberTouched: false,
        consecutiveFailures: 0,
        batchId: '',
        templatePrepared: false,
        storyId: 0,
        preparedStory: null,
        heartbeatTimer: null,
        lastFinalizePayload: null,
        logs: []
    };

    const $story = $('#es-crawler-story');
    const $pattern = $('#es-crawler-url-pattern');
    const $from = $('#es-crawler-range-from');
    const $to = $('#es-crawler-range-to');
    const $padding = $('#es-crawler-padding');
    const $previewNumber = $('#es-crawler-preview-number');
    const $previewNumberField = $('.es-crawler-preview-number-field');
    const $previewUrl = $('#es-crawler-preview-url');
    const $postStatus = $('#es-crawler-post-status');
    const $titleMode = $('#es-crawler-title-mode');
    const $titleTemplate = $('#es-crawler-title-template');
    const $titleTemplateField = $('.es-crawler-title-template-field');
    const $delay = $('#es-crawler-delay');
    const $find = $('#es-crawler-find');
    const $replace = $('#es-crawler-replace');
    const $removeContainer = $('#es-crawler-remove-container');
    const $progressBar = $('.es-crawler-progress-bar');
    const $progressText = $('.es-crawler-progress-text');
    const $currentUrl = $('.es-crawler-current-url');
    const $urlSummary = $('#es-crawler-url-summary');
    const $urlList = $('#es-crawler-url-list');
    const $previewResult = $('#es-crawler-preview-result');
    const $lockNotice = $('#es-crawler-lock-notice');
    const $finalizeStatus = $('#es-crawler-finalize-status');
    const $finalizeBtn = $('#es-crawler-finalize-btn');
    const $logBody = $('#es-crawler-log-body');
    const $logExport = $('#es-crawler-log-export');
    const $logCard = $('.es-crawler-log-card');
    const $helpModal = $('#es-crawler-help-modal');
    const $mode = $('input[name="es_crawler_mode"]');
    const $manualPanel = $('.es-crawler-manual-panel');
    const $manualActions = $('.es-crawler-manual-action');
    const $templatePanel = $('.es-crawler-template-panel');
    const $templateId = $('#es-crawler-template-id');
    const $storySourceUrl = $('#es-crawler-story-source-url');
    const $templatePattern = $('#es-crawler-template-url-pattern');
    const $templateFrom = $('#es-crawler-template-range-from');
    const $templateTo = $('#es-crawler-template-range-to');
    const $templatePadding = $('#es-crawler-template-padding');
    const $templatePrepareBtn = $('#es-crawler-template-prepare-btn');
    const $templatePrepareStatus = $('#es-crawler-template-prepare-status');
    const $templateSummary = $('#es-crawler-template-summary');
    let templateAutoSelectTimer = null;
    let templateAutoSelectRequestId = 0;

    function ajax(action, data) {
        return $.ajax({
            url: cfg.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: Object.assign({ action: action, nonce: cfg.nonce }, data || {})
        }).then(function (response) {
            if (response && response.success === false) {
                return $.Deferred().reject({
                    data: response.data || {},
                    responseJSON: response
                }).promise();
            }

            return response;
        });
    }

    function sleep(ms) {
        return new Promise(resolve => window.setTimeout(resolve, ms));
    }

    function crawlDelayMs() {
        const fallback = parseInt(cfg.default_delay, 10) || 5000;
        const seconds = parseInt($delay.val(), 10);
        if (!Number.isInteger(seconds) || seconds < 1) {
            return fallback;
        }

        return Math.min(seconds, 60) * 1000;
    }

    function selectedStoryId() {
        return parseInt($story.val(), 10) || 0;
    }

    function padNumber(value, width) {
        const raw = String(value);
        const target = parseInt(width, 10) || 0;
        return target > 0 ? raw.padStart(target, '0') : raw;
    }

    function buildUrl(number) {
        return $pattern.val().trim().replace('{n}', padNumber(number, $padding.val()));
    }

    function isHttpUrl(value) {
        try {
            const parsed = new URL(value);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (err) {
            return false;
        }
    }

    function validateBase() {
        const storyId = selectedStoryId();
        const pattern = $pattern.val().trim();
        const from = parseInt($from.val(), 10);
        const to = parseInt($to.val(), 10);

        if (!storyId) {
            throw new Error('Thiếu truyện: vui lòng chọn truyện cần thêm chương.');
        }
        if (!pattern) {
            throw new Error('Thiếu mẫu URL: vui lòng nhập URL chương và dùng {n} tại vị trí số chương.');
        }
        if (pattern.indexOf('{n}') === -1) {
            throw new Error('Mẫu URL không hợp lệ: cần có {n}, ví dụ https://example.com/truyen/chuong-{n}/.');
        }
        if (!Number.isInteger(from) || from < 1) {
            throw new Error('Ô "Từ" không hợp lệ: vui lòng nhập số chương bắt đầu lớn hơn 0.');
        }
        if (!Number.isInteger(to) || to < 1) {
            throw new Error('Ô "Đến" không hợp lệ: vui lòng nhập số chương kết thúc lớn hơn 0.');
        }
        if (to < from) {
            throw new Error('Khoảng chương không hợp lệ: ô "Đến" phải lớn hơn hoặc bằng ô "Từ".');
        }
        if (!isHttpUrl(pattern.replace('{n}', padNumber(from, $padding.val())))) {
            throw new Error('Mẫu URL không hợp lệ: URL tạo ra phải bắt đầu bằng http:// hoặc https://.');
        }

        return { storyId, from, to };
    }

    function buildQueue() {
        const base = validateBase();
        const count = base.to - base.from + 1;
        const max = parseInt(cfg.max_batch_size, 10) || 200;

        if (count > max) {
            throw new Error('Số URL vượt quá giới hạn batch: ' + max + '.');
        }

        const queue = [];
        for (let chapter = base.from; chapter <= base.to; chapter += 1) {
            queue.push({
                chapterNumber: chapter,
                url: buildUrl(chapter),
                retries: 0,
                completed: false
            });
        }

        return queue;
    }

    function updateGeneratedPanel(queue) {
        if (!queue.length) {
            $urlSummary.text('Chưa tạo URL nào.');
            $urlList.val('');
            return;
        }

        const first = queue.slice(0, 3).map(item => item.url);
        const last = queue.slice(Math.max(queue.length - 3, 0)).map(item => item.url);
        const summary = ['Tổng: ' + queue.length, 'Đầu danh sách:'].concat(first, ['Cuối danh sách:'], last).join('\n');
        $urlSummary.text('Đã tạo ' + queue.length + ' URL.');
        $urlList.val(summary + '\n\nTất cả URL:\n' + queue.map(item => item.url).join('\n'));
    }

    function clearRunOutput(options) {
        options = options || {};
        state.logs = [];
        state.lastFinalizePayload = null;
        $logBody.empty();
        $logExport.val('');
        $finalizeStatus.empty();
        $finalizeBtn.addClass('is-hidden');
        $currentUrl.text('Chưa xử lý URL nào.');

        if (!options.keepPreview) {
            $previewResult.text('Chưa có kết quả xem thử.');
        }

        if (!options.keepTemplateStatus) {
            setTemplateStatus('', '');
            clearTemplateSummary();
        }
    }

    function clearPreviewOutput() {
        $previewResult.text('Chưa có kết quả xem thử.');
    }

    function clearGeneratedQueue(reason, options) {
        if ((!state.queue.length && !state.templatePrepared) || state.isRunning || state.batchId) {
            return;
        }

        state.queue = [];
        state.index = 0;
        state.processed = 0;
        state.templatePrepared = false;
        state.preparedStory = null;
        updateGeneratedPanel(state.queue);
        updateProgress();
        clearRunOutput(options);
        setButtons();
        if (reason) {
            setNotice(reason, 'warning');
        }
    }

    function clearCrawlerContext(reason, options) {
        if (state.isRunning || state.batchId) {
            return;
        }

        clearRunOutput(options);
        clearGeneratedQueue(reason, options);
        setButtons();
    }

    function replacementRules() {
        const finds = $find.val().split(/\r?\n/);
        const replaces = $replace.val().split(/\r?\n/);
        const rules = [];

        finds.forEach(function (find, index) {
            if (find !== '') {
                rules.push({
                    find: find,
                    replace: replaces[index] || '',
                    regex: false,
                    remove_container: $removeContainer.prop('checked')
                });
            }
        });

        return rules;
    }

    function crawlerMode() {
        return $mode.filter(':checked').val() || 'manual';
    }

    function setTemplateStatus(message, type) {
        $templatePrepareStatus.removeClass('is-error is-success').empty();
        if (!message) {
            return;
        }

        if (type) {
            $templatePrepareStatus.addClass('is-' + type);
        }
        $templatePrepareStatus.text(message);
    }

    function clearTemplateSummary() {
        $templateSummary.addClass('is-hidden').empty();
    }

    function templateStoryTitle() {
        return String($('#es-crawler-template-story-title').val() || (state.preparedStory && state.preparedStory.title) || '').trim();
    }

    function updateTemplateStoryTargetView() {
        const title = state.storyId > 0
            ? String($('#es-crawler-template-story-target-label').data('storyTitle') || $story.find('option:selected').text() || ('#' + state.storyId)).trim()
            : templateStoryTitle();
        const status = state.storyId > 0 ? 'Đã có' : 'Chưa có, sẽ tạo khi bấm Bắt đầu';
        const label = title ? title + ' (' + status + ')' : status;

        $('#es-crawler-template-story-target-label').text(label);
    }

    function initTemplateStoryTargetSelect() {
        const $target = $('#es-crawler-template-story-target');
        if (!$target.length || !$.fn.select2) {
            return;
        }

        $target.select2({
            width: '100%',
            placeholder: 'Tìm truyện đã có...',
            allowClear: true,
            minimumInputLength: 2,
            language: {
                inputTooShort: function () {
                    return 'Nhập ít nhất 2 ký tự để tìm truyện.';
                },
                searching: function () {
                    return 'Đang tìm...';
                },
                noResults: function () {
                    return 'Không tìm thấy truyện phù hợp.';
                }
            },
            ajax: {
                url: cfg.ajax_url,
                dataType: 'json',
                delay: 400,
                data: function (params) {
                    return {
                        action: cfg.story_search_action,
                        nonce: cfg.story_search_nonce,
                        q: params.term || ''
                    };
                },
                processResults: function (data) {
                    return { results: data || [] };
                }
            }
        });
    }

    function renderTemplateSummary(data) {
        const queue = Array.isArray(data.queue) ? data.queue : [];
        const first = queue.length ? queue[0].url : '';
        const last = queue.length ? queue[queue.length - 1].url : '';
        const storyExists = !!data.story_exists || parseInt(data.story_id, 10) > 0;
        const storyStatus = storyExists ? 'Đã có' : 'Chưa có, sẽ tạo khi bấm Bắt đầu';
        const storyTitle = data.story_title || (state.preparedStory && state.preparedStory.title) || '';
        const queueSourceLabels = {
            detected_links: 'Link chương quét từ mục lục',
            pattern_fallback: 'Mẫu URL chương fallback',
            pattern_manual_range: 'Mẫu URL chương fallback'
        };
        const queueSource = queueSourceLabels[data.queue_source] || data.queue_source || 'Không rõ';
        const detectedTotal = parseInt(data.detected_total_chapters, 10) || 0;
        const detectedTotalLabel = detectedTotal > 0 ? detectedTotal + ' chương (ước lượng)' : 'Không phát hiện được';
        const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
        const warningHtml = warnings.length
            ? '<div class="es-crawler-template-summary-warning">' + warnings.map(function (warning) {
                return '<p>' + escapeHtml(warning) + '</p>';
            }).join('') + '</div>'
            : '';
        const selectedStoryOption = storyExists
            ? '<option value="' + escapeHtml(data.story_id || '') + '" selected>' + escapeHtml(storyTitle) + '</option>'
            : '';

        $templateSummary
            .removeClass('is-hidden')
            .html(
                warningHtml +
                '<dl>' +
                '<dt>Template</dt><dd>' + escapeHtml(data.template_name || '') + '</dd>' +
                '<dt>Truyện</dt><dd>' +
                '<div class="es-crawler-template-story-target">' +
                '<span id="es-crawler-template-story-target-label" data-story-title="' + escapeHtml(storyTitle) + '">' + escapeHtml(storyTitle) + ' <small>(' + escapeHtml(storyStatus) + ')</small></span>' +
                '<button type="button" class="button button-small es-crawler-template-story-edit" title="Sửa hoặc chọn truyện đích" aria-label="Sửa hoặc chọn truyện đích"><span class="dashicons dashicons-edit"></span></button>' +
                '</div>' +
                '<div class="es-crawler-template-story-editor is-hidden">' +
                '<label for="es-crawler-template-story-target">Tìm truyện đã có</label>' +
                '<select id="es-crawler-template-story-target" class="regular-text es-crawler-template-story-control">' + selectedStoryOption + '</select>' +
                '<label for="es-crawler-template-story-title">Hoặc tên truyện mới</label>' +
                '<input type="text" id="es-crawler-template-story-title" class="regular-text es-crawler-template-story-control" value="' + escapeHtml(storyTitle) + '" />' +
                '<div class="es-crawler-template-story-actions">' +
                '<button type="button" class="button button-primary es-crawler-template-story-apply">Áp dụng</button>' +
                '<button type="button" class="button es-crawler-template-story-cancel">Hủy</button>' +
                '</div>' +
                '<p class="description">Chọn truyện đã có để cào vào truyện đó, hoặc nhập tên mới nếu truyện chưa tồn tại.</p>' +
                '</div>' +
                '</dd>' +
                '<dt>Nguồn queue</dt><dd>' + escapeHtml(queueSource) + '</dd>' +
                '<dt>Tổng phát hiện</dt><dd>' + escapeHtml(detectedTotalLabel) + '</dd>' +
                '<dt>Số chương kéo về</dt><dd>' + escapeHtml(data.total_chapters || queue.length || 0) + '</dd>' +
                '<dt>URL đầu</dt><dd><code>' + escapeHtml(first) + '</code></dd>' +
                '<dt>URL cuối</dt><dd><code>' + escapeHtml(last) + '</code></dd>' +
                '</dl>'
            );

        initTemplateStoryTargetSelect();
    }

    function fillReplacementRules(rules) {
        rules = Array.isArray(rules) ? rules : [];
        $find.val(rules.map(function (rule) {
            return rule.find || '';
        }).join('\n'));
        $replace.val(rules.map(function (rule) {
            return rule.replace || '';
        }).join('\n'));
        $removeContainer.prop('checked', rules.some(function (rule) {
            return !!rule.remove_container;
        }));
    }

    function normalizeHost(value) {
        try {
            return new URL(value).hostname.replace(/^www\./, '').toLowerCase();
        } catch (err) {
            return '';
        }
    }

    function autoSelectTemplateByUrl() {
        const host = normalizeHost($storySourceUrl.val().trim());
        if (!host) {
            return;
        }

        const $match = $templateId.find('option').filter(function () {
            return String($(this).data('domain') || '').replace(/^www\./, '').toLowerCase() === host;
        }).first();

        if ($match.length) {
            $templateId.val($match.val());
            syncTemplatePatternView();
            return;
        }

        clearTimeout(templateAutoSelectTimer);
        const requestId = ++templateAutoSelectRequestId;
        templateAutoSelectTimer = setTimeout(function () {
            ajax(cfg.template_search_action, { q: host }).then(function (response) {
                if (requestId !== templateAutoSelectRequestId || $templateId.val()) {
                    return;
                }

                const results = Array.isArray(response && response.results) ? response.results : [];
                const match = results.find(function (item) {
                    return String(item.domain || '').replace(/^www\./, '').toLowerCase() === host;
                });
                if (!match) {
                    return;
                }

                const option = new Option(match.text || ('#' + match.id), match.id, true, true);
                $(option).attr('data-domain', match.domain || '');
                $(option).attr('data-chapter-url-pattern', match.chapter_url_pattern || '');
                $(option).data('chapter-url-pattern', match.chapter_url_pattern || '');
                $templateId.append(option).trigger('change');
                syncTemplatePatternView();
            });
        }, 300);
    }

    function selectedTemplatePattern() {
        const selectedData = $.fn.select2 && $templateId.length ? ($templateId.select2('data')[0] || null) : null;
        if (selectedData && selectedData.chapter_url_pattern) {
            return String(selectedData.chapter_url_pattern).trim();
        }

        const $selected = $templateId.find('option:selected');
        return String($selected.data('chapter-url-pattern') || '').trim();
    }

    function storySlugFromUrl(url) {
        try {
            const parsed = new URL(url);
            const parts = parsed.pathname.replace(/\/+$/, '').split('/').filter(Boolean);
            return parts.length ? parts[parts.length - 1] : '';
        } catch (err) {
            return '';
        }
    }

    function resolveTemplatePattern(pattern, chapterNumber) {
        const storyUrl = $storySourceUrl.val().trim();
        const storyBase = storyUrl.replace(/\/+$/, '');
        const storySlug = storySlugFromUrl(storyUrl);
        const number = padNumber(chapterNumber || 1, $templatePadding.val());

        return String(pattern || '')
            .replace(/\{story_url\}/g, storyBase || '{story_url}')
            .replace(/\{story_slug\}/g, storySlug || '{story_slug}')
            .replace(/\{chapter_number\}/g, number)
            .replace(/\{n\}/g, number);
    }

    function syncTemplatePatternView() {
        const pattern = selectedTemplatePattern();
        const from = parseInt($templateFrom.val(), 10) || 1;
        const resolved = resolveTemplatePattern(pattern, from);
        $templatePattern.val(resolved);
        if (crawlerMode() === 'template') {
            $previewNumber.val(from);
            $previewUrl.val(resolved.indexOf('{') === -1 ? resolved : '');
        }
    }

    function templateRangePayload() {
        const from = parseInt($templateFrom.val(), 10);
        const to = parseInt($templateTo.val(), 10);
        const padding = parseInt($templatePadding.val(), 10) || 0;

        if (!Number.isInteger(from) || from < 1) {
            throw new Error('Ô "Từ" trong Template không hợp lệ.');
        }
        if (!Number.isInteger(to) || to < 1) {
            throw new Error('Ô "Đến" trong Template không hợp lệ.');
        }
        if (to < from) {
            throw new Error('Khoảng chương Template không hợp lệ: ô "Đến" phải lớn hơn hoặc bằng ô "Từ".');
        }

        return {
            range_from: from,
            range_to: to,
            padding: padding
        };
    }

    async function prepareTemplateQueue(options) {
        options = options || {};
        const templateId = parseInt($templateId.val(), 10) || 0;
        const storyUrl = $storySourceUrl.val().trim();
        if (!templateId) {
            throw new Error('Hãy chọn template.');
        }
        if (!storyUrl) {
            throw new Error('Hãy nhập URL trang truyện.');
        }

        const response = await ajax(cfg.template_prepare_action, Object.assign({
            template_id: templateId,
            story_url: storyUrl
        }, templateRangePayload()));
        const data = response.data || {};

        state.queue = Array.isArray(data.queue) ? data.queue : [];
        state.index = 0;
        state.processed = 0;
        state.consecutiveFailures = 0;
        state.storyId = parseInt(data.story_id, 10) || 0;
        state.preparedStory = data.prepared_story || null;
        state.templatePrepared = state.queue.length > 0;

        if (state.storyId) {
            const option = new Option(data.story_title || ('#' + state.storyId), state.storyId, true, true);
            $story.append(option).trigger('change');
        } else {
            $story.val(null).trigger('change');
        }
        if (options.applyTemplateDefaults !== false && data.delay_between) {
            $delay.val(data.delay_between);
        }
        if (data.chapter_url_pattern) {
            $templatePattern.val(resolveTemplatePattern(data.chapter_url_pattern, state.queue.length ? state.queue[0].chapterNumber : 1));
            if (data.chapter_url_pattern.indexOf('{story_url}') === -1 && data.chapter_url_pattern.indexOf('{story_slug}') === -1) {
                $pattern.val(data.chapter_url_pattern.replace('{chapter_number}', '{n}'));
            }
        }
        if (state.queue.length) {
            $from.val(state.queue[0].chapterNumber || 1);
            $to.val(state.queue[state.queue.length - 1].chapterNumber || state.queue.length);
            $templateFrom.val(state.queue[0].chapterNumber || 1);
            $templateTo.val(state.queue[state.queue.length - 1].chapterNumber || state.queue.length);
            $previewNumber.val(state.queue[0].chapterNumber || 1);
            $previewUrl.val(state.queue[0].url || '');
        }

        if (options.applyTemplateDefaults !== false) {
            fillReplacementRules(data.find_replace_rules || []);
        }
        updateGeneratedPanel(state.queue);
        updateProgress();
        setButtons();

        return data;
    }

    async function ensureStoryBeforeStart() {
        if (crawlerMode() !== 'template') {
            return selectedStoryId();
        }

        if (state.storyId > 0) {
            return state.storyId;
        }

        if (!state.preparedStory) {
            throw new Error('Thiếu thông tin truyện: vui lòng kiểm tra queue hoặc bấm Bắt đầu để crawler quét lại từ Template.');
        }

        const storyTitle = templateStoryTitle();
        if (!storyTitle) {
            throw new Error('Tên truyện sẽ tạo không được để trống.');
        }

        setNotice('Đang tạo truyện mới...', 'warning');
        log(null, 'story', 'Đang tạo truyện mới: ' + storyTitle, 0);

        const response = await ajax(cfg.template_ensure_story_action, {
            story_title: storyTitle,
            story_author: state.preparedStory.author || '',
            story_desc: state.preparedStory.desc || '',
            story_thumb: state.preparedStory.thumb || '',
            story_cats: JSON.stringify(state.preparedStory.cats || []),
            story_url: state.preparedStory.source_url || $storySourceUrl.val().trim()
        });
        const data = response.data || {};
        state.storyId = parseInt(data.story_id, 10) || 0;
        if (!state.storyId) {
            throw new Error('Không tạo hoặc tìm được truyện để bắt đầu batch.');
        }

        const option = new Option(data.story_title || ('#' + state.storyId), state.storyId, true, true);
        $story.append(option).trigger('change');
        log(null, 'story', (data.story_created ? 'Đã tạo truyện mới: ' : 'Đã tìm thấy truyện: ') + (data.story_title || storyTitle), 0);
        setNotice('Đã có truyện. Đang bắt đầu chạy cào...', 'success');

        return state.storyId;
    }

    function toggleCrawlerMode() {
        const isTemplate = crawlerMode() === 'template';
        $templatePanel.toggleClass('is-hidden', !isTemplate);
        $manualPanel.toggleClass('is-hidden', isTemplate);
        $manualActions.toggleClass('is-hidden', isTemplate);
        $previewNumberField.toggleClass('is-hidden', isTemplate);
    }

    function titleOptions() {
        return {
            title_mode: $titleMode.val() || 'auto',
            title_template: $titleTemplate.val() || ''
        };
    }

    function toggleTitleTemplate() {
        $titleTemplateField.toggleClass('is-hidden', $titleMode.val() !== 'custom');
    }

    function syncPreviewNumberFromRange(force) {
        const from = parseInt($from.val(), 10);
        if (!Number.isInteger(from) || from < 1) {
            return;
        }

        if (force || !state.previewNumberTouched) {
            $previewNumber.val(from);
        }
    }

    function openHelpModal() {
        $helpModal.removeClass('is-hidden').attr('aria-hidden', 'false');
    }

    function closeHelpModal() {
        $helpModal.addClass('is-hidden').attr('aria-hidden', 'true');
    }

    function setButtons() {
        const isTemplate = crawlerMode() === 'template';
        const canStart = isTemplate
            ? (parseInt($templateId.val(), 10) > 0 && $storySourceUrl.val().trim() !== '')
            : state.queue.length > 0;
        $('#es-crawler-start-btn').prop('disabled', state.isRunning || !canStart);
        $('#es-crawler-pause-btn').prop('disabled', !state.isRunning).text(state.isPaused ? 'Tiếp tục' : 'Tạm dừng');
        $('#es-crawler-stop-btn').prop('disabled', !state.isRunning && !state.batchId);
        setFormLocked(state.isRunning || !!state.batchId);
    }

    function setFormLocked(locked) {
        $('input[name="es_crawler_mode"], #es-crawler-story, #es-crawler-url-pattern, #es-crawler-range-from, #es-crawler-range-to, #es-crawler-padding, #es-crawler-template-range-from, #es-crawler-template-range-to, #es-crawler-template-padding, #es-crawler-preview-number, #es-crawler-post-status, #es-crawler-title-mode, #es-crawler-title-template, #es-crawler-delay, #es-crawler-preview-url, #es-crawler-find, #es-crawler-replace, #es-crawler-generate-btn, #es-crawler-preview-btn, #es-crawler-template-id, #es-crawler-story-source-url, #es-crawler-template-prepare-btn, .es-crawler-template-story-control, .es-crawler-template-story-edit, .es-crawler-template-story-apply, .es-crawler-template-story-cancel')
            .prop('disabled', locked);

        if ($story.length && $.fn.select2) {
            $story.prop('disabled', locked).trigger('change.select2');
        }
        if ($.fn.select2) {
            $('#es-crawler-template-story-target').prop('disabled', locked).trigger('change.select2');
        }
    }

    function scrollToProgress() {
        const target = $('.es-crawler-status-card');
        if (!target.length) {
            return;
        }

        $('html, body').animate({ scrollTop: Math.max(target.offset().top - 40, 0) }, 300);
    }

    function scrollToPreview() {
        const target = $('#es-crawler-preview-result').closest('.es-crawler-card');
        if (!target.length) {
            return;
        }

        $('html, body').animate({ scrollTop: Math.max(target.offset().top - 40, 0) }, 300);
    }

    function scrollToLog() {
        if (!$logCard.length) {
            return;
        }

        $('html, body').animate({ scrollTop: Math.max($logCard.offset().top - 40, 0) }, 300);
    }

    function updateProgress() {
        const total = state.queue.length;
        const percent = total ? Math.round((state.processed / total) * 100) : 0;
        $progressBar.css('width', percent + '%');
        $progressText.text(state.processed + ' / ' + total + ' (' + percent + '%)');
    }

    function setNotice(message, type) {
        if (!message) {
            $lockNotice.addClass('is-hidden').removeClass('notice-error notice-warning notice-success').empty();
            return;
        }

        $lockNotice.removeClass('is-hidden notice-error notice-warning notice-success')
            .addClass(type ? 'notice-' + type : 'notice-warning')
            .text(message);
    }

    function log(item, status, message, retry, extra) {
        extra = extra || {};
        const entry = {
            chapter: item ? item.chapterNumber : '',
            url: item ? item.url : '',
            retry: retry || 0,
            status: status,
            message: message,
            chapterId: extra.chapter_id || 0,
            contentLength: extra.content_length || 0,
            warnings: extra.warnings || []
        };
        state.logs.push(entry);

        const badge = '<span class="es-crawler-badge es-crawler-badge--' + escapeHtml(status) + '">' + escapeHtml(status) + '</span>';
        const detail = escapeHtml(message) + (entry.chapterId ? ' | chương #' + entry.chapterId : '') + (entry.contentLength ? ' | ' + entry.contentLength + ' ký tự' : '');
        $logBody.prepend('<tr><td>' + escapeHtml(entry.chapter) + '</td><td>' + badge + '</td><td>' + escapeHtml(entry.retry) + '</td><td>' + detail + '</td><td><code>' + escapeHtml(entry.url) + '</code></td></tr>');
        $logExport.val(state.logs.map(formatLog).join('\n'));
    }

    function formatLog(entry) {
        return '[' + entry.status + '] chuong=' + entry.chapter + ' thu_lai=' + entry.retry + ' chapter_id=' + entry.chapterId + ' do_dai=' + entry.contentLength + ' url=' + entry.url + ' thong_bao=' + entry.message + (entry.warnings.length ? ' canh_bao=' + entry.warnings.join('; ') : '');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function errorMessage(response, fallback) {
        if (response && response.message) {
            return response.message;
        }
        if (response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message) {
            return response.responseJSON.data.message;
        }
        if (response && response.data && response.data.message) {
            return response.data.message;
        }
        return fallback || 'Request thất bại.';
    }

    function responseData(response) {
        if (response && response.responseJSON && response.responseJSON.data) {
            return response.responseJSON.data;
        }
        if (response && response.data) {
            return response.data;
        }

        return {};
    }

    function previewDebugHtml(response) {
        const data = responseData(response);
        const debug = data.debug && data.debug.template_selectors ? data.debug.template_selectors : null;
        if (!debug) {
            return '';
        }

        const before = debug.before_cleanup || {};
        const after = debug.after_cleanup || {};
        const row = function (label, value) {
            return '<dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value == null || value === '' ? '-' : value) + '</dd>';
        };

        return '<details class="es-crawler-preview-debug" open>' +
            '<summary>Debug selector</summary>' +
            '<dl class="es-crawler-preview-meta">' +
            row('URL fetch', debug.source_url || before.url || '') +
            row('URL clean', debug.clean_url || '') +
            row('Title HTML', after.html_title || before.html_title || '') +
            row('Kích thước HTML', debug.body_length ? debug.body_length + ' bytes' : '') +
            row('Scope selector', debug.scope_selector || '') +
            row('Content selector', debug.content_selector || '') +
            row('Scope trước cleanup', before.scope_match_count) +
            row('Scope sau cleanup', after.scope_match_count) +
            row('Content toàn trang trước cleanup', before.content_match_count) +
            row('Content toàn trang sau cleanup', after.content_match_count) +
            row('Content trong scope trước cleanup', before.content_in_scope_match_count) +
            row('Content trong scope sau cleanup', after.content_in_scope_match_count) +
            '</dl>' +
            '</details>';
    }

    function batchSummary() {
        const counts = state.logs.reduce(function (summary, entry) {
            const status = entry.status || 'unknown';
            summary[status] = (summary[status] || 0) + 1;
            return summary;
        }, {});

        const total = state.queue.length;
        const success = counts.success || 0;
        const duplicate = counts.duplicate || 0;
        const failed = counts.failed || 0;
        const skipped = counts.skipped || 0;
        const retry = counts.retry || 0;

        if (failed || duplicate || skipped) {
            const parts = [];
            if (success) {
                parts.push('thành công: ' + success);
            }
            if (duplicate) {
                parts.push('trùng/bỏ qua: ' + duplicate);
            }
            if (skipped) {
                parts.push('bỏ qua: ' + skipped);
            }
            if (failed) {
                parts.push('lỗi: ' + failed);
            }
            if (retry) {
                parts.push('thử lại: ' + retry);
            }

            return {
                hasIssue: true,
                message: 'Đã hoàn tất batch nhưng có mục không thành công. ' + parts.join(' | ') + '.'
            };
        }

        return {
            hasIssue: false,
            message: 'Đã hoàn tất batch thành công' + (total ? ' (' + success + '/' + total + ' URL).' : '.')
        };
    }

    function renderFinalizeSummary() {
        const summary = batchSummary();
        $finalizeStatus.empty().append($('<span/>').text(summary.message));

        if (summary.hasIssue) {
            $('<button/>', {
                type: 'button',
                class: 'button button-small es-crawler-view-log-btn',
                text: 'Xem log',
                click: scrollToLog
            }).appendTo($finalizeStatus);
        }
    }

    function startHeartbeat() {
        stopHeartbeat();
        state.heartbeatTimer = window.setInterval(function () {
            if (!state.batchId) {
                return;
            }
            ajax(cfg.heartbeat_action, { batch_id: state.batchId }).fail(function (xhr) {
                state.isRunning = false;
                state.isPaused = false;
                stopHeartbeat();
                setButtons();
                setNotice(errorMessage(xhr, 'Heartbeat thất bại. Batch đã dừng.'), 'error');
            });
        }, parseInt(cfg.heartbeat_interval, 10) || 30000);
    }

    function stopHeartbeat() {
        if (state.heartbeatTimer) {
            window.clearInterval(state.heartbeatTimer);
            state.heartbeatTimer = null;
        }
    }

    async function processQueue() {
        while (state.isRunning && state.index < state.queue.length) {
            if (state.isPaused) {
                await sleep(250);
                continue;
            }

            const item = state.queue[state.index];
            $currentUrl.text('Đang xử lý chương ' + item.chapterNumber + ': ' + item.url);

            try {
                const response = await ajax(cfg.process_action, Object.assign({
                    batch_id: state.batchId,
                    story_id: state.storyId,
                    source_url: item.url,
                    chapter_number: item.chapterNumber,
                    post_status: $postStatus.val(),
                    replace_rules: JSON.stringify(replacementRules()),
                    template_id: crawlerMode() === 'template' ? ($templateId.val() || '') : ''
                }, titleOptions()));

                const payload = response.data || {};
                item.completed = true;
                state.index += 1;
                state.processed += 1;
                state.consecutiveFailures = 0;
                log(item, payload.status || 'success', payload.message || 'Đã xử lý.', item.retries, payload);
                updateProgress();
                await sleep(crawlDelayMs());
            } catch (xhr) {
                item.retries += 1;
                if (item.retries <= (parseInt(cfg.max_retries, 10) || 3)) {
                    log(item, 'retry', errorMessage(xhr, 'Xử lý thất bại, đang thử lại.'), item.retries);
                    await sleep(parseInt(cfg.retry_delay, 10) || 3000);
                    continue;
                }

                item.completed = true;
                state.index += 1;
                state.processed += 1;
                state.consecutiveFailures += 1;
                log(item, 'failed', errorMessage(xhr, 'Xử lý thất bại.'), item.retries);
                updateProgress();

                if (state.consecutiveFailures >= 3) {
                    state.isRunning = false;
                    state.isPaused = false;
                    setButtons();
                    setNotice('Batch đã tự dừng vì có 3 URL lỗi liên tiếp. Kiểm tra lại khoảng chương hoặc URL nguồn.', 'error');
                    scrollToProgress();
                    await finalizeBatch('Batch tự dừng sau 3 URL lỗi liên tiếp.');
                    return;
                }
            }
        }

        if (state.isRunning && state.index >= state.queue.length) {
            state.isRunning = false;
            state.isPaused = false;
            setButtons();
            await finalizeBatch('Queue batch đã hoàn tất.');
        }
    }

    async function finalizeBatch(reason) {
        if (!state.storyId) {
            return;
        }

        $finalizeStatus.text('Đang hoàn tất: ' + reason);
        state.lastFinalizePayload = { story_id: state.storyId, batch_id: state.batchId };

        try {
            const response = await ajax(cfg.finalize_action, state.lastFinalizePayload);
            const data = response.data || {};
            stopHeartbeat();
            state.batchId = '';
            state.isRunning = false;
            state.isPaused = false;
            $finalizeBtn.addClass('is-hidden');
            renderFinalizeSummary();
            setNotice('', 'success');
            setButtons();
        } catch (xhr) {
            $finalizeBtn.removeClass('is-hidden');
            $finalizeStatus.text('Hoàn tất thất bại: ' + errorMessage(xhr, 'Hoàn tất thất bại.'));
            setButtons();
        }
    }

    function initSelect2() {
        if (!$story.length || !$.fn.select2) {
            return;
        }

        $story.select2({
            width: '100%',
            placeholder: $story.data('placeholder') || 'Tìm truyện...',
            allowClear: true,
            minimumInputLength: 2,
            language: {
                inputTooShort: function () {
                    return 'Nhập ít nhất 2 ký tự để tìm truyện.';
                },
                searching: function () {
                    return 'Đang tìm...';
                },
                noResults: function () {
                    return 'Không tìm thấy truyện phù hợp.';
                }
            },
            ajax: {
                url: cfg.ajax_url,
                dataType: 'json',
                delay: 400,
                data: function (params) {
                    return {
                        action: cfg.story_search_action,
                        nonce: cfg.story_search_nonce,
                        q: params.term || ''
                    };
                },
                processResults: function (data) {
                    return { results: data || [] };
                }
            }
        });
    }

    function initTemplateSelect2() {
        if (!$templateId.length || !$.fn.select2) {
            return;
        }

        $templateId.select2({
            width: '100%',
            placeholder: $templateId.data('placeholder') || 'Tìm template...',
            allowClear: true,
            minimumInputLength: 1,
            language: {
                inputTooShort: function () {
                    return 'Nhập ít nhất 1 ký tự để tìm template.';
                },
                searching: function () {
                    return 'Đang tìm...';
                },
                noResults: function () {
                    return 'Không tìm thấy template phù hợp.';
                }
            },
            ajax: {
                url: cfg.ajax_url,
                method: 'POST',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        action: cfg.template_search_action,
                        nonce: cfg.nonce,
                        q: params.term || ''
                    };
                },
                processResults: function (data) {
                    return data || { results: [] };
                }
            }
        });
    }

    $templateSummary.on('click', '.es-crawler-template-story-edit', function () {
        $('.es-crawler-template-story-editor').removeClass('is-hidden');
        $('#es-crawler-template-story-title').trigger('focus').trigger('select');
    });

    $templateSummary.on('click', '.es-crawler-template-story-cancel', function () {
        $('.es-crawler-template-story-editor').addClass('is-hidden');
    });

    $templateSummary.on('click', '.es-crawler-template-story-apply', function () {
        const $target = $('#es-crawler-template-story-target');
        const selectedId = parseInt($target.val(), 10) || 0;
        const selectedData = $.fn.select2 && $target.length ? ($target.select2('data')[0] || null) : null;
        const selectedTitle = selectedData && selectedData.text ? String(selectedData.text).trim() : '';
        const newTitle = String($('#es-crawler-template-story-title').val() || '').trim();

        if (selectedId > 0) {
            state.storyId = selectedId;
            $('#es-crawler-template-story-target-label')
                .data('storyTitle', selectedTitle || ('#' + selectedId));

            const option = new Option(selectedTitle || ('#' + selectedId), selectedId, true, true);
            $story.append(option).trigger('change');
        } else {
            if (!newTitle) {
                setNotice('Tên truyện mới không được để trống.', 'error');
                return;
            }

            state.storyId = 0;
            if (state.preparedStory) {
                state.preparedStory.title = newTitle;
            }
            $story.val(null).trigger('change');
            $('#es-crawler-template-story-target-label')
                .data('storyTitle', newTitle);
        }

        updateTemplateStoryTargetView();
        $('.es-crawler-template-story-editor').addClass('is-hidden');
        setNotice('Đã cập nhật truyện đích.', 'success');
    });

    $('#es-crawler-generate-btn').on('click', function () {
        try {
            if (crawlerMode() === 'template') {
                throw new Error('Chế độ Template tự tạo danh sách URL khi bấm Kiểm tra queue hoặc Bắt đầu.');
            }
            clearRunOutput();
            state.queue = buildQueue();
            state.templatePrepared = false;
            state.index = 0;
            state.processed = 0;
            updateGeneratedPanel(state.queue);
            updateProgress();
            setNotice('', 'success');
        } catch (err) {
            setNotice(err.message, 'error');
            scrollToProgress();
        }
    });

    $mode.on('change', function () {
        toggleCrawlerMode();
        clearCrawlerContext('Chế độ cào đã thay đổi. Vui lòng kiểm tra lại queue trước khi bắt đầu nếu cần.');
    });
    $storySourceUrl.on('input change', function () {
        autoSelectTemplateByUrl();
        syncTemplatePatternView();
        if (crawlerMode() === 'template') {
            clearCrawlerContext('URL trang truyện đã thay đổi. Vui lòng kiểm tra lại queue nếu cần.');
        }
    });
    $templateId.on('change', function () {
        syncTemplatePatternView();
        if (crawlerMode() === 'template') {
            clearCrawlerContext('Template đã thay đổi. Vui lòng kiểm tra lại queue nếu cần.');
        }
    });
    $templateId.on('select2:select', function (event) {
        const item = event.params && event.params.data ? event.params.data : {};
        const pattern = item.chapter_url_pattern || '';
        const $selected = $templateId.find('option:selected');
        $selected.data('chapter-url-pattern', pattern);
        $selected.attr('data-chapter-url-pattern', pattern);
        syncTemplatePatternView();
    });
    $templateFrom.add($templateTo).add($templatePadding).on('input change', function () {
        syncTemplatePatternView();
        if (crawlerMode() === 'template') {
            clearPreviewOutput();
            if (state.templatePrepared) {
                state.queue = [];
                state.index = 0;
                state.processed = 0;
                state.templatePrepared = false;
                updateGeneratedPanel(state.queue);
                updateProgress();
                setButtons();
                setNotice('Khoảng chương Template đã thay đổi. Bấm Kiểm tra queue để xem lại hoặc Bắt đầu để quét queue mới nhất.', 'warning');
            }
        }
    });

    $templatePrepareBtn.on('click', async function () {
        const templateId = parseInt($templateId.val(), 10) || 0;
        const storyUrl = $storySourceUrl.val().trim();
        if (!templateId) {
            setTemplateStatus('Hãy chọn template.', 'error');
            return;
        }
        if (!storyUrl) {
            setTemplateStatus('Hãy nhập URL trang truyện.', 'error');
            return;
        }

        try {
            templateRangePayload();
        } catch (err) {
            setTemplateStatus(err.message || 'Khoảng chương Template không hợp lệ.', 'error');
            return;
        }

        const originalText = $templatePrepareBtn.text();
        $templatePrepareBtn.prop('disabled', true).text('Đang kiểm tra...');
        clearRunOutput({ keepTemplateStatus: true });
        setTemplateStatus('', '');

        try {
            const data = await prepareTemplateQueue({ applyTemplateDefaults: true });
            const detectedTotal = parseInt(data.detected_total_chapters, 10) || 0;
            setTemplateStatus(data.message || 'Đã kiểm tra queue từ Template.', 'success');
            renderTemplateSummary(data);
            if (detectedTotal <= 0) {
                $templateTo.trigger('focus').trigger('select');
            }
            setNotice('', 'success');
        } catch (xhr) {
            state.templatePrepared = false;
            setTemplateStatus(errorMessage(xhr, 'Kiểm tra queue thất bại.'), 'error');
        } finally {
            $templatePrepareBtn.prop('disabled', false).text(originalText);
        }
    });

    $('#es-crawler-preview-btn').on('click', async function () {
        try {
            const isTemplateMode = crawlerMode() === 'template';
            const firstQueueItem = state.queue.length ? state.queue[0] : null;
            const storyId = isTemplateMode ? selectedStoryId() : validateBase().storyId;
            const fallbackChapterNumber = isTemplateMode
                ? (firstQueueItem ? firstQueueItem.chapterNumber : 1)
                : validateBase().from;
            const chapterNumber = parseInt($previewNumber.val(), 10) || fallbackChapterNumber;
            const url = $previewUrl.val().trim() || (firstQueueItem ? firstQueueItem.url : '') || buildUrl(chapterNumber);

            if (isTemplateMode && !$templateId.val()) {
                throw new Error('Hãy chọn template trước khi xem thử.');
            }
            if (isTemplateMode && !state.templatePrepared && !$previewUrl.val().trim()) {
                throw new Error('Hãy kiểm tra queue từ Template trước khi xem thử, hoặc nhập URL xem thử thủ công.');
            }
            const previewStoryTitle = isTemplateMode
                ? templateStoryTitle()
                : '';
            if (!storyId && !previewStoryTitle) {
                throw new Error('Thiếu tên truyện: hãy kiểm tra queue từ Template hoặc nhập tên truyện sẽ tạo.');
            }
            if (!isHttpUrl(url)) {
                throw new Error('URL xem thử không hợp lệ: vui lòng nhập URL bắt đầu bằng http:// hoặc https://.');
            }
            $previewResult.html('<p>Đang tải bản xem thử...</p>');
            scrollToPreview();

            const response = await ajax(cfg.preview_action, Object.assign({
                story_id: storyId,
                preview_story_title: previewStoryTitle,
                source_url: url,
                chapter_number: chapterNumber,
                replace_rules: JSON.stringify(replacementRules()),
                template_id: isTemplateMode ? ($templateId.val() || '') : ''
            }, titleOptions()));

            const data = response.data || {};
            const warnings = data.warnings && data.warnings.length ? '<div class="notice notice-warning inline"><p>' + escapeHtml(data.warnings.join(' | ')) + '</p></div>' : '';
            const sourceTitle = data.source_title || data.title || '';
            const finalTitle = data.final_title || data.title || '';
            $previewResult.html(
                warnings +
                '<div class="es-crawler-preview-title-box">' +
                '<div class="es-crawler-preview-title-row">' +
                '<span class="es-crawler-preview-title-label">Tiêu đề nguồn</span>' +
                '<span class="es-crawler-preview-title-value">' + escapeHtml(sourceTitle || '(trống)') + '</span>' +
                '</div>' +
                '<div class="es-crawler-preview-title-row es-crawler-preview-title-row--final">' +
                '<span class="es-crawler-preview-title-label">Tiêu đề sẽ lưu</span>' +
                '<strong class="es-crawler-preview-title-value">' + escapeHtml(finalTitle || '(trống)') + '</strong>' +
                '</div>' +
                '</div>' +
                '<dl class="es-crawler-preview-meta">' +
                '<dt>URL đã làm sạch</dt><dd><code>' + escapeHtml(data.clean_url) + '</code></dd>' +
                '<dt>Tên miền</dt><dd>' + escapeHtml(data.domain) + '</dd>' +
                '<dt>Luật nhận diện</dt><dd>' + escapeHtml(data.rule_label) + '</dd>' +
                '<dt>Độ dài</dt><dd>' + escapeHtml(data.content_length) + ' ký tự</dd>' +
                '</dl>' +
                '<div class="es-crawler-preview-html">' + (data.content_preview_html || '') + '</div>'
            );
        } catch (xhr) {
            $previewResult.html(
                '<div class="notice notice-error inline"><p>' + escapeHtml(errorMessage(xhr, 'Xem thử thất bại.')) + '</p></div>' +
                previewDebugHtml(xhr)
            );
            scrollToPreview();
        }
    });

    $('#es-crawler-start-btn').on('click', async function () {
        try {
            if (crawlerMode() === 'template') {
                const data = await prepareTemplateQueue({ applyTemplateDefaults: false });
                setTemplateStatus(data.message || 'Đã tạo queue mới nhất từ Template.', 'success');
                renderTemplateSummary(data);
            } else if (!state.queue.length) {
                state.queue = buildQueue();
                updateGeneratedPanel(state.queue);
            }

            state.index = 0;
            state.processed = 0;
            state.consecutiveFailures = 0;
            clearRunOutput({ keepPreview: true, keepTemplateStatus: true });
            updateProgress();
            setNotice('', 'success');
            state.storyId = await ensureStoryBeforeStart();
            if (!state.storyId) {
                throw new Error('Thiếu truyện: hãy kiểm tra queue từ Template hoặc chọn truyện cần thêm chương.');
            }

            const response = await ajax(cfg.start_batch_action, {
                story_id: state.storyId,
                expected_total: state.queue.length
            });
            state.batchId = response.data.batch_id;
            state.isRunning = true;
            state.isPaused = false;
            startHeartbeat();
            setButtons();
            log(null, 'batch', 'Đã bắt đầu batch ' + state.batchId, 0);
            setNotice('Đang chạy cào chương...', 'success');
            scrollToProgress();
            processQueue();
        } catch (xhr) {
            setNotice(errorMessage(xhr, xhr.message || 'Không thể bắt đầu crawler.'), 'error');
            scrollToProgress();
        }
    });

    $('#es-crawler-pause-btn').on('click', function () {
        if (!state.isRunning) {
            return;
        }
        state.isPaused = !state.isPaused;
        setButtons();
        log(null, state.isPaused ? 'paused' : 'resumed', state.isPaused ? 'Queue đã tạm dừng.' : 'Queue đã tiếp tục.', 0);
        if (!state.isPaused) {
            processQueue();
        }
    });

    $('#es-crawler-stop-btn').on('click', async function () {
        state.isRunning = false;
        state.isPaused = false;
        setButtons();

        if (state.batchId) {
            try {
                await ajax(cfg.stop_batch_action, { batch_id: state.batchId });
                log(null, 'stopped', 'Batch đã dừng và lock đã được giải phóng.', 0);
                stopHeartbeat();
                state.batchId = '';
                await finalizeBatch('Người dùng đã dừng.');
            } catch (xhr) {
                setNotice(errorMessage(xhr, 'Dừng thất bại.'), 'error');
                scrollToProgress();
            }
        }
    });

    $finalizeBtn.on('click', function () {
        if (state.lastFinalizePayload) {
            finalizeBatch('Thử hoàn tất lại thủ công.');
        }
    });

    $('#es-crawler-copy-log-btn').on('click', function () {
        $logExport.trigger('select');
        document.execCommand('copy');
    });

    $('#es-crawler-help-open').on('click', openHelpModal);
    $('[data-es-crawler-help-close]').on('click', closeHelpModal);
    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && !$helpModal.hasClass('is-hidden')) {
            closeHelpModal();
        }
    });

    $titleMode.on('change', toggleTitleTemplate);
    $from.on('input change', function () {
        const previousPreviewNumber = String($previewNumber.val() || '');
        syncPreviewNumberFromRange(false);
        if (crawlerMode() === 'manual') {
            const previewChanged = String($previewNumber.val() || '') !== previousPreviewNumber;
            clearCrawlerContext('Khoảng chương đã thay đổi. Vui lòng tạo lại danh sách URL trước khi bắt đầu.', {
                keepPreview: !previewChanged
            });
        }
    });
    $to.on('input change', function () {
        if (crawlerMode() === 'manual') {
            clearCrawlerContext('Khoảng chương đã thay đổi. Vui lòng tạo lại danh sách URL trước khi bắt đầu.', {
                keepPreview: true
            });
        }
    });
    $padding.add($pattern).on('input change', function () {
        if (crawlerMode() === 'manual') {
            clearCrawlerContext('Mẫu URL hoặc cách đệm số đã thay đổi. Vui lòng tạo lại danh sách URL trước khi bắt đầu.');
        }
    });
    $previewNumber.on('input change', function () {
        state.previewNumberTouched = true;
        clearPreviewOutput();
    });
    $previewUrl.on('input change', function () {
        clearPreviewOutput();
    });

    $(function () {
        initSelect2();
        initTemplateSelect2();
        toggleCrawlerMode();
        toggleTitleTemplate();
        syncTemplatePatternView();
        syncPreviewNumberFromRange(true);
        setButtons();
        updateProgress();
    });
})(jQuery);
