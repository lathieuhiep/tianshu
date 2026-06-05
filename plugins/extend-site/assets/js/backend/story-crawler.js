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
        storyId: 0,
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
    const $helpModal = $('#es-crawler-help-modal');

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

    function clearGeneratedQueue(reason) {
        if (!state.queue.length || state.isRunning || state.batchId) {
            return;
        }

        state.queue = [];
        state.index = 0;
        state.processed = 0;
        updateGeneratedPanel(state.queue);
        updateProgress();
        if (reason) {
            setNotice(reason, 'warning');
        }
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
        $('#es-crawler-start-btn').prop('disabled', state.isRunning);
        $('#es-crawler-pause-btn').prop('disabled', !state.isRunning).text(state.isPaused ? 'Tiếp tục' : 'Tạm dừng');
        $('#es-crawler-stop-btn').prop('disabled', !state.isRunning && !state.batchId);
        setFormLocked(state.isRunning || !!state.batchId);
    }

    function setFormLocked(locked) {
        $('#es-crawler-story, #es-crawler-url-pattern, #es-crawler-range-from, #es-crawler-range-to, #es-crawler-padding, #es-crawler-preview-number, #es-crawler-post-status, #es-crawler-title-mode, #es-crawler-title-template, #es-crawler-delay, #es-crawler-preview-url, #es-crawler-find, #es-crawler-replace, #es-crawler-generate-btn, #es-crawler-preview-btn')
            .prop('disabled', locked);

        if ($story.length && $.fn.select2) {
            $story.prop('disabled', locked).trigger('change.select2');
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

    function formatStatusCounts(counts, publishedCount) {
        counts = counts || {};
        const publish = counts.publish != null ? counts.publish : (publishedCount || 0);
        const draft = counts.draft || 0;
        const total = Object.keys(counts).reduce(function (sum, key) {
            return sum + (parseInt(counts[key], 10) || 0);
        }, 0);

        return 'Đã hoàn tất. Đã xuất bản: ' + publish + ' | Bản nháp: ' + draft + ' | Tổng chương: ' + total;
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
                    replace_rules: JSON.stringify(replacementRules())
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
            $finalizeBtn.addClass('is-hidden');
            $finalizeStatus.text(formatStatusCounts(data.chapter_status_counts, data.chapter_count));
            setNotice('', 'success');
        } catch (xhr) {
            $finalizeBtn.removeClass('is-hidden');
            $finalizeStatus.text('Hoàn tất thất bại: ' + errorMessage(xhr, 'Hoàn tất thất bại.'));
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

    $('#es-crawler-generate-btn').on('click', function () {
        try {
            state.queue = buildQueue();
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

    $('#es-crawler-preview-btn').on('click', async function () {
        try {
            const base = validateBase();
            const chapterNumber = parseInt($previewNumber.val(), 10) || base.from;
            const url = $previewUrl.val().trim() || buildUrl(chapterNumber);
            if (!isHttpUrl(url)) {
                throw new Error('URL xem thử không hợp lệ: vui lòng nhập URL bắt đầu bằng http:// hoặc https://.');
            }
            $previewResult.html('<p>Đang tải bản xem thử...</p>');
            scrollToPreview();

            const response = await ajax(cfg.preview_action, Object.assign({
                story_id: base.storyId,
                source_url: url,
                chapter_number: chapterNumber,
                replace_rules: JSON.stringify(replacementRules()),
                allow_short_content: true
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
            $previewResult.html('<div class="notice notice-error inline"><p>' + escapeHtml(errorMessage(xhr, 'Xem thử thất bại.')) + '</p></div>');
            scrollToPreview();
        }
    });

    $('#es-crawler-start-btn').on('click', async function () {
        try {
            if (!state.queue.length) {
                state.queue = buildQueue();
                updateGeneratedPanel(state.queue);
            }

            state.storyId = selectedStoryId();
            state.index = 0;
            state.processed = 0;
            state.consecutiveFailures = 0;
            state.logs = [];
            $logBody.empty();
            $logExport.val('');
            updateProgress();
            setNotice('', 'success');

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
        syncPreviewNumberFromRange(false);
        clearGeneratedQueue('Khoảng chương đã thay đổi. Vui lòng tạo lại danh sách URL trước khi bắt đầu.');
    });
    $to.add($padding).add($pattern).on('input change', function () {
        clearGeneratedQueue('Mẫu URL hoặc khoảng chương đã thay đổi. Vui lòng tạo lại danh sách URL trước khi bắt đầu.');
    });
    $previewNumber.on('input change', function () {
        state.previewNumberTouched = true;
    });

    $(function () {
        initSelect2();
        toggleTitleTemplate();
        syncPreviewNumberFromRange(true);
        setButtons();
        updateProgress();
    });
})(jQuery);
