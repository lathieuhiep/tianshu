(function ($) {
    'use strict';

    const cfg = window.esCrawlerTemplate || {};
    const $form = $('#es-crawler-template-form');
    const $targetUrl = $('#es-template-target-url');
    const $chapterUrl = $('#es-template-chapter-url');
    const $previewBtn = $('#es-template-load-preview');
    const $chapterPreviewBtn = $('#es-template-load-chapter-preview');
    const $previewStatus = $('#es-template-preview-status');
    const $previewFrame = $('#es-template-preview-frame');
    const $testBtn = $('#es-template-test-parse');
    const $testResult = $('#es-template-test-result');
    const $templateId = $('#es-template-id');
    const $templateExisting = $('#es-template-existing');
    const $saveBtn = $('#es-template-save');
    const $saveStatus = $('#es-template-save-status');
    let previewRequestId = 0;

    function ajax(action, data) {
        return $.ajax({
            url: cfg.ajax_url,
            method: 'POST',
            dataType: 'json',
            cache: false,
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

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function errorMessage(response, fallback) {
        if (response && response.responseJSON && response.responseJSON.data && response.responseJSON.data.message) {
            return response.responseJSON.data.message;
        }
        if (response && response.data && response.data.message) {
            return response.data.message;
        }
        return fallback || (cfg.i18n && cfg.i18n.request_failed) || 'Request lỗi.';
    }

    function setStatus($target, message, type) {
        $target.removeClass('is-error is-success').empty();
        if (!message) {
            return;
        }

        if (type) {
            $target.addClass('is-' + type);
        }
        $target.text(message);
    }

    function sampleUrl() {
        return $targetUrl.val().trim();
    }

    function chapterSampleUrl() {
        return $chapterUrl.val().trim();
    }

    function replacementRules() {
        const finds = $('#es-template-find-replace-find').val().split(/\r?\n/);
        const replaces = $('#es-template-find-replace-replace').val().split(/\r?\n/);
        const removeContainer = $('#es-template-find-replace-remove-container').prop('checked');
        const rules = [];

        finds.forEach(function (find, index) {
            if (find !== '') {
                rules.push({
                    find: find,
                    replace: replaces[index] || '',
                    regex: false,
                    remove_container: removeContainer
                });
            }
        });

        return rules;
    }

    function collectSelectors() {
        const data = {};
        $('#es-template-find-replace-rules').val(JSON.stringify(replacementRules()));

        $form.find('input[name], select[name], textarea[name]').each(function () {
            const $field = $(this);
            data[$field.attr('name')] = $field.val();
        });
        data.sample_story_url = sampleUrl();
        data.sample_chapter_url = chapterSampleUrl();

        return data;
    }

    function setSaveStatus(message, type) {
        setStatus($saveStatus, message, type);
    }

    function renderTemplateOptions(templates, selectedId) {
        if (!Array.isArray(templates)) {
            return;
        }

        let html = '<option value="">Chọn mẫu để sửa</option>';
        templates.forEach(function (template) {
            const id = String(template.id || '');
            html += '<option value="' + escapeHtml(id) + '" data-domain="' + escapeHtml(template.domain || '') + '"' + (String(selectedId || '') === id ? ' selected' : '') + '>' +
                escapeHtml((template.name || '') + ' - ' + (template.domain || '')) +
                '</option>';
        });
        $templateExisting.html(html);
        if ($.fn.select2) {
            $templateExisting.trigger('change.select2');
        }
    }

    function setTemplateSelectOption(template) {
        if (!template || !template.id) {
            $templateExisting.html('<option value="">Chọn mẫu để sửa</option>').val('');
            if ($.fn.select2) {
                $templateExisting.trigger('change.select2');
            }
            return;
        }

        const id = String(template.id);
        const text = (template.name || '') + ' - ' + (template.domain || '');
        const option = new Option(text, id, true, true);
        $(option).attr('data-domain', template.domain || '');
        $templateExisting.find('option[value="' + id.replace(/"/g, '\\"') + '"]').remove();
        $templateExisting.append(option).val(id);
        if ($.fn.select2) {
            $templateExisting.trigger('change.select2');
        }
    }

    function setTemplateId(id) {
        const value = parseInt(id, 10) || 0;
        $templateId.val(value);
        $templateExisting.val(value > 0 ? String(value) : '');
        if ($.fn.select2) {
            $templateExisting.trigger('change.select2');
        }
    }


    function fillReplacementRules(rules) {
        rules = Array.isArray(rules) ? rules : [];
        $('#es-template-find-replace-find').val(rules.map(function (rule) {
            return rule.find || '';
        }).join('\n'));
        $('#es-template-find-replace-replace').val(rules.map(function (rule) {
            return rule.replace || '';
        }).join('\n'));
        $('#es-template-find-replace-remove-container').prop('checked', rules.some(function (rule) {
            return !!rule.remove_container;
        }));
        $('#es-template-find-replace-rules').val(JSON.stringify(rules));
    }

    function fillTemplate(template) {
        template = template || {};
        setTemplateId(template.id || 0);
        $('#es-template-name').val(template.name || '');
        $('#es-template-domain').val(template.domain || '');
        $targetUrl.val(template.sample_story_url || '');
        $chapterUrl.val(template.sample_chapter_url || '');
        $('#es-template-toc-type').val(template.toc_type || 'selector');
        $('#es-template-delay-between').val(template.delay_between || 1);
        $('#es-template-chapter-link-selector').val(template.chapter_link_selector || '');
        $('#es-template-toc-page-link-selector').val(template.toc_page_link_selector || '');
        $('#es-template-chapter-url-pattern').val(template.chapter_url_pattern || '');
        $('#es-template-chapter-content-scope-selector').val(template.chapter_content_scope_selector || '');
        $('#es-template-chapter-title-selector').val(template.chapter_title_selector || '');
        $('#es-template-chapter-content-selector').val(template.chapter_content_selector || '');

        const rules = template.story_extract_rules || {};
        Object.keys(rules).forEach(function (field) {
            const rule = rules[field] || {};
            const $field = $('[data-extract-field="' + field + '"]');
            $field.find('.es-template-extract-selector-input').val(rule.selector || '');
            $('#' + field + '-label').val(rule.label || '');
            $('#' + field + '-value-mode').val(rule.value_mode || 'node_text');
        });

        fillReplacementRules(template.find_replace_rules || []);
        setSaveStatus('Đã tải mẫu: ' + (template.name || ''), 'success');
    }

    async function loadTemplate(id) {
        id = parseInt(id, 10) || 0;
        if (!id) {
            setTemplateId(0);
            return;
        }

        setSaveStatus('Đang tải mẫu...', '');
        const response = await ajax(cfg.load_action, { template_id: id });
        fillTemplate((response.data && response.data.template) || {});
    }


    function writePreview(html) {
        const iframe = $previewFrame.get(0);
        if (!iframe) {
            return;
        }

        if ('srcdoc' in iframe) {
            iframe.srcdoc = html;
            return;
        }

        try {
            iframe.src = 'about:blank';
            iframe.contentDocument.open();
            iframe.contentDocument.write(html);
            iframe.contentDocument.close();
        } catch (e) {
            iframe.src = 'about:blank';
        }
    }

    function clearPreview(message) {
        writePreview(
            '<!doctype html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;padding:16px;color:#50575e;">' +
            escapeHtml(message || '') +
            '</body></html>'
        );
    }

    function renderTestResult(data) {
        if (!data || !data.html) {
            $testResult.addClass('is-error').text(errorMessage(null));
            return;
        }

        $testResult.removeClass('is-error').html(data.html);
    }

    function validateTemplateBeforeSave(data) {
        if (!String(data.chapter_content_scope_selector || '').trim()) {
            return 'Thiếu selector khối bọc nội dung chương.';
        }

        const chapterUrlPattern = String(data.chapter_url_pattern || '').trim();
        if (!chapterUrlPattern) {
            return 'Mẫu URL chương là bắt buộc vì crawler dùng mẫu này để tạo URL từng chương.';
        }
        if (chapterUrlPattern.indexOf('{chapter_number}') === -1 && chapterUrlPattern.indexOf('{n}') === -1) {
            return 'Mẫu URL chương phải có biến số chương {chapter_number} hoặc {n}. Ví dụ: {story_url}/chuong-{chapter_number}/';
        }

        return '';
    }

    function defaultChapterUrlPattern() {
        return '{story_url}/chuong-{chapter_number}/';
    }

    function chapterPatternWarning(pattern) {
        pattern = String(pattern || '').trim();
        if (!pattern) {
            return '';
        }

        if (pattern.indexOf('{story_url}') === -1 && pattern.indexOf('{story_slug}') === -1) {
            return 'Lưu ý: Mẫu URL chương không có {story_url} hoặc {story_slug}, nên có thể chỉ dùng được cho một truyện cụ thể.';
        }

        return '';
    }

    function normalizeCompareUrl(value) {
        try {
            const parsed = new URL(String(value || '').trim());
            parsed.pathname = parsed.pathname.replace(/\/{2,}/g, '/').replace(/\/+$/, '');
            return parsed.toString().replace(/\/+$/, '');
        } catch (err) {
            return String(value || '').trim().replace(/\/+$/, '');
        }
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

    function chapterNumberFromUrl(url) {
        const value = String(url || '');
        const match = value.match(/(?:chuong|chapter|chap|tap)(?:[\s/_-]|=)*0*([0-9]+)/i) || value.match(/(?:\/|-|_)([0-9]+)(?:\.html?)?\/?$/i);
        return match ? parseInt(match[1], 10) || 1 : 1;
    }

    function buildChapterUrlFromPattern(pattern, storyUrl, chapterUrl) {
        const chapterNumber = chapterNumberFromUrl(chapterUrl);
        const storyBase = normalizeCompareUrl(storyUrl);
        const storySlug = storySlugFromUrl(storyUrl);
        return String(pattern || '')
            .replace(/\{story_url\}/g, storyBase)
            .replace(/\{story_slug\}/g, storySlug)
            .replace(/\{chapter_number\}/g, String(chapterNumber))
            .replace(/\{n\}/g, String(chapterNumber));
    }

    function replaceChapterNumberToken(value) {
        const replaced = String(value || '').replace(/((?:chuong|chapter|chap|tap)(?:[\s/_-]|=)*)0*([0-9]+)/i, '$1{chapter_number}');
        if (replaced !== value) {
            return replaced;
        }

        return String(value || '').replace(/([\/_-])0*([0-9]+)(\.html?)?(\/?)$/i, '$1{chapter_number}$3$4');
    }

    function inferChapterUrlPattern() {
        const storyUrl = sampleUrl();
        const chapterUrl = chapterSampleUrl();
        if (!storyUrl) {
            return { error: 'Nhập URL trang truyện / mục lục trước.' };
        }
        if (!chapterUrl) {
            return { error: 'Nhập URL chương mẫu trước.' };
        }

        const storyBase = normalizeCompareUrl(storyUrl);
        const chapterBase = normalizeCompareUrl(chapterUrl);
        let pattern = '';
        if (chapterBase.indexOf(storyBase + '/') === 0 || chapterBase.indexOf(storyBase + '?') === 0 || chapterBase.indexOf(storyBase + '#') === 0) {
            pattern = '{story_url}' + chapterBase.slice(storyBase.length);
        } else {
            try {
                const storyParsed = new URL(storyUrl);
                const chapterParsed = new URL(chapterUrl);
                storyParsed.pathname = storyParsed.pathname.replace(/\/{2,}/g, '/').replace(/\/+$/, '');
                chapterParsed.pathname = chapterParsed.pathname.replace(/\/{2,}/g, '/').replace(/\/+$/, '');
                const normalizedStory = storyParsed.toString().replace(/\/+$/, '');
                const normalizedChapter = chapterParsed.toString().replace(/\/+$/, '');
                if (normalizedChapter.indexOf(normalizedStory + '/') === 0 || normalizedChapter.indexOf(normalizedStory + '?') === 0 || normalizedChapter.indexOf(normalizedStory + '#') === 0) {
                    pattern = '{story_url}' + normalizedChapter.slice(normalizedStory.length);
                }
            } catch (err) {
                pattern = '';
            }

            if (!pattern) {
                const storySlug = storySlugFromUrl(storyUrl);
                pattern = chapterBase;
                if (storySlug) {
                    pattern = pattern.replace(storySlug, '{story_slug}');
                }
            }
        }

        pattern = replaceChapterNumberToken(pattern);
        if (pattern.indexOf('{chapter_number}') === -1) {
            return { error: 'Không nhận ra vị trí số chương trong URL chương mẫu.' };
        }

        return { pattern: pattern };
    }

    function updateChapterPatternCheck() {
        const $check = $('#es-template-chapter-url-pattern-check');
        if (!$check.length) {
            return;
        }

        const pattern = $('#es-template-chapter-url-pattern').val().trim();
        const storyUrl = sampleUrl();
        const chapterUrl = chapterSampleUrl();
        $check.removeClass('is-error is-success').empty();

        if (!pattern || !storyUrl || !chapterUrl) {
            return;
        }

        const builtUrl = buildChapterUrlFromPattern(pattern, storyUrl, chapterUrl);
        if (normalizeCompareUrl(builtUrl) === normalizeCompareUrl(chapterUrl)) {
            $check.addClass('is-success').text('Mẫu URL chương khớp với URL chương mẫu.');
            return;
        }

        $check.addClass('is-error').html(
            'Mẫu URL chương tạo ra URL khác URL chương mẫu.<br>' +
            'URL tạo ra: <code>' + escapeHtml(builtUrl) + '</code><br>' +
            'URL chương mẫu: <code>' + escapeHtml(chapterUrl) + '</code>'
        );
    }

    function chapterPatternMismatchMessage(pattern) {
        const storyUrl = sampleUrl();
        const chapterUrl = chapterSampleUrl();
        if (!pattern || !storyUrl || !chapterUrl) {
            return '';
        }

        const builtUrl = buildChapterUrlFromPattern(pattern, storyUrl, chapterUrl);
        if (normalizeCompareUrl(builtUrl) === normalizeCompareUrl(chapterUrl)) {
            return '';
        }

        return 'Mẫu URL chương có vẻ chưa khớp với URL chương mẫu.\n\n' +
            'URL tạo ra:\n' + builtUrl + '\n\n' +
            'URL chương mẫu:\n' + chapterUrl + '\n\n' +
            'Bấm OK để vẫn lưu, hoặc Cancel để quay lại sửa Mẫu URL chương.';
    }

    async function runStructuredSelectorTest() {
        const storyUrl = sampleUrl();
        const chapterUrl = chapterSampleUrl();
        if (!storyUrl) {
            $testResult.addClass('is-error').text('Nhập URL trang truyện / mục lục trước.');
            return;
        }
        if (!chapterUrl) {
            $testResult.addClass('is-error').text('Nhập URL chương mẫu trước.');
            return;
        }

        const originalText = $testBtn.text();
        $testBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.test_loading) || 'Đang test selector...');
        $testResult.removeClass('is-error').text((cfg.i18n && cfg.i18n.test_loading) || 'Đang test selector...');

        try {
            const payload = Object.assign({ target_url: storyUrl, story_url: storyUrl, chapter_url: chapterUrl }, collectSelectors());
            const response = await ajax(cfg.test_parse_action, payload);
            renderTestResult(response.data || {});
        } catch (xhr) {
            $testResult.addClass('is-error').text(errorMessage(xhr));
        } finally {
            $testBtn.prop('disabled', false).text(originalText);
        }
    }

    function initTemplateSelect2() {
        if (!$templateExisting.length || !$.fn.select2) {
            return;
        }

        $templateExisting.select2({
            width: '100%',
            placeholder: 'Tìm mẫu crawler...',
            allowClear: true,
            minimumInputLength: 1,
            language: {
                inputTooShort: function () {
                    return 'Nhập ít nhất 1 ký tự để tìm mẫu.';
                },
                searching: function () {
                    return 'Đang tìm...';
                },
                noResults: function () {
                    return 'Không tìm thấy mẫu phù hợp.';
                }
            },
            ajax: {
                url: cfg.ajax_url,
                method: 'POST',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        action: cfg.search_action,
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

    $form.on('focus', '.es-template-selector-input', function () {
        const $field = $(this);
        if (!$field.data('original-set')) {
            $field.data('original', $field.val());
            $field.data('original-set', true);
        }
    });

    $form.on('click', '.es-template-reset-field', function () {
        const target = $(this).data('target');
        const $field = $(target);
        if (!$field.length) {
            return;
        }

        $field.val($field.data('original') || '').trigger('change');
    });

    $('#es-template-chapter-url-pattern').on('blur', function () {
        const warning = chapterPatternWarning($(this).val());
        if (warning) {
            setSaveStatus(warning, 'error');
        }
        updateChapterPatternCheck();
    });

    $('#es-template-chapter-url-pattern, #es-template-target-url, #es-template-chapter-url').on('input change blur', function () {
        updateChapterPatternCheck();
    });

    $('#es-template-build-pattern-from-preview').on('click', function () {
        const inferred = inferChapterUrlPattern();
        if (inferred.error) {
            setSaveStatus(inferred.error, 'error');
            if (!sampleUrl()) {
                $targetUrl.trigger('focus');
            } else if (!chapterSampleUrl()) {
                $chapterUrl.trigger('focus');
            } else {
                $('#es-template-chapter-url-pattern').trigger('focus');
            }
            return;
        }

        $('#es-template-chapter-url-pattern').val(inferred.pattern).trigger('change');
        updateChapterPatternCheck();
        setSaveStatus('Đã tạo Mẫu URL chương từ URL chương mẫu. Hãy kiểm tra lại trước khi lưu.', 'success');
    });

    $templateExisting.on('change', async function () {
        const id = parseInt($(this).val(), 10) || 0;
        if (!id) {
            setTemplateId(0);
            return;
        }

        try {
            await loadTemplate(id);
        } catch (xhr) {
            setSaveStatus(errorMessage(xhr), 'error');
            setTemplateId(0);
        }
    });

    $saveBtn.on('click', async function () {
        const originalText = $saveBtn.text();
        $saveBtn.prop('disabled', true).text('Đang lưu...');
        setSaveStatus('', '');

        try {
            const payload = collectSelectors();
            const validationError = validateTemplateBeforeSave(payload);
            if (validationError) {
                setSaveStatus(validationError, 'error');
                return;
            }

            const mismatchMessage = chapterPatternMismatchMessage(payload.chapter_url_pattern);
            if (mismatchMessage && !window.confirm(mismatchMessage)) {
                $('#es-template-chapter-url-pattern').trigger('focus');
                setSaveStatus('Đã dừng lưu để bạn chỉnh Mẫu URL chương.', 'error');
                return;
            }

            const response = await ajax(cfg.save_action, payload);
            const data = response.data || {};
            setTemplateSelectOption(data.template || null);
            fillTemplate(data.template || {});
            setSaveStatus(data.message || 'Đã lưu mẫu.', 'success');
        } catch (xhr) {
            setSaveStatus(errorMessage(xhr), 'error');
        } finally {
            $saveBtn.prop('disabled', false).text(originalText);
        }
    });


    $previewBtn.on('click', async function () {
        const url = sampleUrl();
        if (!url) {
            setStatus($previewStatus, (cfg.i18n && cfg.i18n.missing_url) || 'Nhập URL truyện mẫu trước.', 'error');
            return;
        }

        const originalText = $previewBtn.text();
        const requestId = ++previewRequestId;
        $previewBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.loading) || 'Đang tải...');
        setStatus($previewStatus, '', '');
        clearPreview((cfg.i18n && cfg.i18n.loading) || 'Đang tải...');

        try {
            const response = await ajax(cfg.preview_proxy_action, { target_url: url, cache_buster: Date.now() });
            if (requestId !== previewRequestId || sampleUrl() !== url) {
                return;
            }

            writePreview((response.data && response.data.html) || '');
            const previewUrl = (response.data && response.data.target_url) || url;
            setStatus($previewStatus, ((cfg.i18n && cfg.i18n.preview_loaded) || 'Đã tải xem trước.') + ' ' + previewUrl, 'success');
        } catch (xhr) {
            if (requestId !== previewRequestId) {
                return;
            }

            clearPreview('');
            setStatus($previewStatus, errorMessage(xhr), 'error');
        } finally {
            if (requestId === previewRequestId) {
                $previewBtn.prop('disabled', false).text(originalText);
            }
        }
    });

    $chapterPreviewBtn.on('click', async function () {
        const url = chapterSampleUrl();
        if (!url) {
            setStatus($previewStatus, 'Nhập URL chương mẫu trước.', 'error');
            return;
        }

        const originalText = $chapterPreviewBtn.text();
        const requestId = ++previewRequestId;
        $chapterPreviewBtn.prop('disabled', true).text('Đang tải...');
        setStatus($previewStatus, '', '');
        clearPreview('Đang tải...');

        try {
            const response = await ajax(cfg.preview_proxy_action, { target_url: url, cache_buster: Date.now() });
            if (requestId !== previewRequestId || chapterSampleUrl() !== url) {
                return;
            }

            writePreview((response.data && response.data.html) || '');
            const previewUrl = (response.data && response.data.target_url) || url;
            setStatus($previewStatus, 'Đã tải xem trước. ' + previewUrl, 'success');
        } catch (xhr) {
            if (requestId !== previewRequestId) {
                return;
            }

            clearPreview('');
            setStatus($previewStatus, errorMessage(xhr), 'error');
        } finally {
            if (requestId === previewRequestId) {
                $chapterPreviewBtn.prop('disabled', false).text(originalText);
            }
        }
    });

    $testBtn.on('click', async function () {
        await runStructuredSelectorTest();
    });

    initTemplateSelect2();

    if ($templateExisting.val()) {
        $templateExisting.trigger('change');
    }

})(jQuery);
