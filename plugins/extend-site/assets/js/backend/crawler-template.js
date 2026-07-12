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
    const $newBtn = $('#es-template-new');
    const $deleteBtn = $('#es-template-delete');
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
    }

    function setTemplateId(id) {
        const value = parseInt(id, 10) || 0;
        $templateId.val(value);
        $deleteBtn.prop('disabled', value <= 0);
        $templateExisting.val(value > 0 ? String(value) : '');
    }

    function clearForm() {
        const targetUrl = $targetUrl.val();
        const chapterUrl = $chapterUrl.val();
        $form.get(0).reset();
        $targetUrl.val(targetUrl);
        $chapterUrl.val(chapterUrl);
        $('#es-template-chapter-url-pattern').val(defaultChapterUrlPattern());
        setTemplateId(0);
        $('#es-template-find-replace-rules').val('[]');
        updateChapterPatternCheck();
        setSaveStatus('', '');
        $testResult.empty().removeClass('is-error');
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

    async function deleteTemplate(id) {
        id = parseInt(id, 10) || 0;
        if (!id || !window.confirm('Xóa mẫu crawler này?')) {
            return;
        }

        const response = await ajax(cfg.delete_action, { template_id: id });
        renderTemplateOptions((response.data && response.data.templates) || [], 0);
        clearForm();
        setSaveStatus((response.data && response.data.message) || 'Đã xóa mẫu.', 'success');
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
        renderStructuredTestResult(data);
        return;

        const warnings = Array.isArray(data.warnings) && data.warnings.length
            ? '<ul class="es-template-warning-list">' + data.warnings.map(function (item) {
                return '<li>' + escapeHtml(item) + '</li>';
            }).join('') + '</ul>'
            : '';

        const cats = Array.isArray(data.story_cats) ? data.story_cats.join(', ') : '';
        const links = Array.isArray(data.chapter_link_samples) ? data.chapter_link_samples : [];
        const matched = data.matched || {};
        const matchSamples = data.match_samples || {};

        let html = '<div class="es-template-result-summary">';
        html += '<strong>Truyện:</strong> ' + escapeHtml(data.story_title || '(trống)');
        html += '<br><strong>Tác giả:</strong> ' + escapeHtml(data.story_author || '(trống)');
        html += '<br><strong>Link chương:</strong> ' + escapeHtml(data.chapter_link_count || 0) + (data.chapter_link_estimated ? ' (ước tính)' : '');
        html += '<br><strong>Trang mục lục:</strong> ' + escapeHtml(data.toc_pages_scanned || 1) + '/' + escapeHtml(data.toc_page_count || 0);
        html += warnings;
        html += '</div>';

        html += '<table class="widefat striped es-template-result-table"><tbody>';
        html += resultRow('Story URL', data.target_url || '');
        html += resultRow('Mô tả', data.story_desc || '');
        html += resultRow('Độ dài mô tả', data.story_desc_length || 0);
        html += resultRow('Ảnh bìa', data.story_thumb || '');
        html += resultRow('Thể loại', cats);
        html += resultRow('Tên chương', data.chapter_title || '');
        html += resultRow('Độ dài nội dung chương', data.chapter_content_length || 0);
        html += '</tbody></table>';

        html += '<h3>Số phần tử khớp selector</h3>';
        html += '<table class="widefat striped es-template-result-table"><tbody>';
        Object.keys(matched).forEach(function (key) {
            html += resultRow(key, matched[key]);
        });
        html += '</tbody></table>';

        html += '<h3>Mau phan tu khop selector</h3>';
        html += '<table class="widefat striped es-template-result-table"><thead><tr><th>Muc</th><th>Phan tu khop</th></tr></thead><tbody>';
        Object.keys(matchSamples).forEach(function (key) {
            const samples = Array.isArray(matchSamples[key]) ? matchSamples[key] : [];
            if (!samples.length) {
                return;
            }

            html += '<tr><th scope="row">' + escapeHtml(key) + '</th><td>';
            html += samples.map(function (sample) {
                const node = sample && sample.node ? sample.node : '';
                const text = sample && sample.text ? sample.text : '';
                return '<div><code>' + escapeHtml(node) + '</code>' + (text ? ' - ' + escapeHtml(text) : '') + '</div>';
            }).join('');
            html += '</td></tr>';
        });
        html += '</tbody></table>';

        if (links.length) {
            html += '<h3>Mẫu link chương</h3>';
            html += '<table class="widefat striped es-template-result-table"><thead><tr><th>Text</th><th>URL</th></tr></thead><tbody>';
            links.forEach(function (item) {
                html += '<tr><td>' + escapeHtml(item.text || '') + '</td><td><code>' + escapeHtml(item.href || '') + '</code></td></tr>';
            });
            html += '</tbody></table>';
        }

        $testResult.removeClass('is-error').html(html);
    }

    function renderStructuredTestResult(data) {
        const links = Array.isArray(data.chapter_link_samples) ? data.chapter_link_samples : [];
        const fieldResults = Array.isArray(data.field_results) ? data.field_results : [];
        const okCount = fieldResults.filter(function (item) {
            return item && item.status === 'ok';
        }).length;
        const missingCount = fieldResults.filter(function (item) {
            return item && item.status === 'missing';
        }).length;

        let html = '<div class="es-template-result-summary">';
        html += '<strong>Ket qua test:</strong> ' + escapeHtml(okCount) + ' muc co du lieu, ' + escapeHtml(missingCount) + ' muc chua tim thay';
        html += '<br><strong>Truyen:</strong> ' + escapeHtml(data.story_title || '(trong)');
        html += '<br><strong>Tac gia:</strong> ' + escapeHtml(data.story_author || '(trong)');
        html += '<br><strong>Link chuong:</strong> ' + escapeHtml(data.chapter_link_count || 0) + (data.chapter_link_estimated ? ' (uoc tinh)' : '');
        html += '<br><strong>Trang muc luc:</strong> ' + escapeHtml(data.toc_pages_scanned || 1) + '/' + escapeHtml(data.toc_page_count || 0);
        if (fieldResults.length && Number(data.chapter_link_count || 0) < 1) {
            html += '<p class="es-template-result-note">Chua tao duoc danh sach chuong neu khong tim thay link chuong trong HTML goc.</p>';
        }
        html += '</div>';

        html += '<table class="widefat striped es-template-result-table"><tbody>';
        html += resultRow('Story URL', data.target_url || '');
        html += resultRow('Mo ta', data.story_desc || '');
        html += resultRow('Do dai mo ta', data.story_desc_length || 0);
        html += resultRow('Anh bia', data.story_thumb || '');
        html += resultRow('The loai', Array.isArray(data.story_cats) ? data.story_cats.join(', ') : '');
        html += resultRow('Ten chuong', data.chapter_title || '');
        html += resultRow('Do dai noi dung chuong', data.chapter_content_length || 0);
        html += '</tbody></table>';

        html += renderFieldResultGroups(fieldResults);

        if (links.length) {
            html += '<h3>Mau link chuong</h3>';
            html += '<table class="widefat striped es-template-result-table"><thead><tr><th>Text</th><th>URL</th></tr></thead><tbody>';
            links.forEach(function (item) {
                html += '<tr><td>' + escapeHtml(item.text || '') + '</td><td><code>' + escapeHtml(item.href || '') + '</code></td></tr>';
            });
            html += '</tbody></table>';
        }

        $testResult.removeClass('is-error').html(html);
    }

    function renderFieldResultGroups(fieldResults) {
        if (!fieldResults.length) {
            return '';
        }

        const groups = {};
        fieldResults.forEach(function (item) {
            const group = item && item.group ? item.group : 'Khac';
            if (!groups[group]) {
                groups[group] = [];
            }
            groups[group].push(item);
        });

        let html = '<div class="es-template-field-results">';
        Object.keys(groups).forEach(function (group) {
            html += '<section class="es-template-result-group">';
            html += '<h3>' + escapeHtml(group) + '</h3>';
            html += '<table class="widefat striped es-template-result-table es-template-field-result-table">';
            html += '<thead><tr><th>Field</th><th>Trang thai</th><th>Selector</th><th>Ket qua</th></tr></thead><tbody>';
            groups[group].forEach(function (item) {
                const status = item.status === 'ok' ? 'ok' : 'missing';
                const samples = Array.isArray(item.samples) ? item.samples : [];
                html += '<tr class="es-template-field-row is-' + escapeHtml(status) + '">';
                html += '<th scope="row">' + escapeHtml(item.label || '') + '</th>';
                html += '<td><span class="es-template-status-pill is-' + escapeHtml(status) + '">' + (status === 'ok' ? 'Co du lieu' : 'Khong thay') + '</span></td>';
                html += '<td><code>' + escapeHtml(item.selector || '') + '</code></td>';
                html += '<td>' + escapeHtml(item.result || '') + renderSampleNodes(samples);
                if (status === 'missing' && item.hint) {
                    html += '<p class="es-template-result-hint">' + escapeHtml(item.hint) + '</p>';
                }
                html += '</td></tr>';
            });
            html += '</tbody></table></section>';
        });
        html += '</div>';

        return html;
    }

    function renderSampleNodes(samples) {
        if (!samples.length) {
            return '';
        }

        return '<div class="es-template-node-samples">' + samples.map(function (sample) {
            const node = sample && sample.node ? sample.node : '';
            const text = sample && sample.text ? sample.text : '';
            return '<div><code>' + escapeHtml(node) + '</code>' + (text ? ' - ' + escapeHtml(text) : '') + '</div>';
        }).join('') + '</div>';
    }

    function resultRow(label, value) {
        if (Array.isArray(value)) {
            value = value.join(', ');
        }

        return '<tr><th scope="row">' + escapeHtml(label) + '</th><td>' + escapeHtml(value) + '</td></tr>';
    }

    function validateTemplateBeforeSave(data) {
        if (!String(data.chapter_content_scope_selector || '').trim()) {
            return 'Thieu selector khoi boc noi dung chuong.';
        }

        const chapterUrlPattern = String(data.chapter_url_pattern || '').trim();
        if (!chapterUrlPattern) {
            return 'Mau URL chuong la bat buoc vi crawler dung mau nay de tao URL tung chuong.';
        }
        if (chapterUrlPattern.indexOf('{chapter_number}') === -1 && chapterUrlPattern.indexOf('{n}') === -1) {
            return 'Mau URL chuong phai co bien so chuong {chapter_number} hoac {n}. Vi du: {story_url}/chuong-{chapter_number}/';
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
            return 'Luu y: Mau URL chuong khong co {story_url} hoac {story_slug}, nen co the chi dung duoc cho mot truyen cu the.';
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
            return { error: 'Nhap URL trang truyen / muc luc truoc.' };
        }
        if (!chapterUrl) {
            return { error: 'Nhap URL chuong mau truoc.' };
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
            return { error: 'Khong nhan ra vi tri so chuong trong URL chuong mau.' };
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
            $check.addClass('is-success').text('Mau URL chuong khop voi URL chuong mau.');
            return;
        }

        $check.addClass('is-error').html(
            'Mau URL chuong tao ra URL khac URL chuong mau.<br>' +
            'URL tao ra: <code>' + escapeHtml(builtUrl) + '</code><br>' +
            'URL chuong mau: <code>' + escapeHtml(chapterUrl) + '</code>'
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

        return 'Mau URL chuong co ve chua khop voi URL chuong mau.\n\n' +
            'URL tao ra:\n' + builtUrl + '\n\n' +
            'URL chuong mau:\n' + chapterUrl + '\n\n' +
            'Bam OK de van luu, hoac Cancel de quay lai sua Mau URL chuong.';
    }

    async function runStructuredSelectorTest() {
        const storyUrl = sampleUrl();
        const chapterUrl = chapterSampleUrl();
        if (!storyUrl) {
            $testResult.addClass('is-error').text('Nhap URL trang truyen / muc luc truoc.');
            return;
        }
        if (!chapterUrl) {
            $testResult.addClass('is-error').text('Nhap URL chuong mau truoc.');
            return;
        }

        const originalText = $testBtn.text();
        $testBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.test_loading) || 'Dang test selector...');
        $testResult.removeClass('is-error').text((cfg.i18n && cfg.i18n.test_loading) || 'Dang test selector...');

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
        setSaveStatus('Da tao Mau URL chuong tu URL chuong mau. Hay kiem tra lai truoc khi luu.', 'success');
    });

    $newBtn.on('click', function () {
        clearForm();
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
                setSaveStatus('Da dung luu de ban chinh Mau URL chuong.', 'error');
                return;
            }

            const response = await ajax(cfg.save_action, payload);
            const data = response.data || {};
            renderTemplateOptions(data.templates || [], data.template && data.template.id);
            fillTemplate(data.template || {});
            setSaveStatus(data.message || 'Đã lưu mẫu.', 'success');
        } catch (xhr) {
            setSaveStatus(errorMessage(xhr), 'error');
        } finally {
            $saveBtn.prop('disabled', false).text(originalText);
        }
    });

    $deleteBtn.on('click', async function () {
        const id = parseInt($templateId.val(), 10) || 0;
        if (!id) {
            return;
        }

        const originalText = $deleteBtn.text();
        $deleteBtn.prop('disabled', true).text('Đang xóa...');

        try {
            await deleteTemplate(id);
        } catch (xhr) {
            setSaveStatus(errorMessage(xhr), 'error');
            $deleteBtn.prop('disabled', false);
        } finally {
            $deleteBtn.text(originalText);
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
            setStatus($previewStatus, 'Nhap URL chuong mau truoc.', 'error');
            return;
        }

        const originalText = $chapterPreviewBtn.text();
        const requestId = ++previewRequestId;
        $chapterPreviewBtn.prop('disabled', true).text('Dang tai...');
        setStatus($previewStatus, '', '');
        clearPreview('Dang tai...');

        try {
            const response = await ajax(cfg.preview_proxy_action, { target_url: url, cache_buster: Date.now() });
            if (requestId !== previewRequestId || chapterSampleUrl() !== url) {
                return;
            }

            writePreview((response.data && response.data.html) || '');
            const previewUrl = (response.data && response.data.target_url) || url;
            setStatus($previewStatus, 'Da tai xem truoc. ' + previewUrl, 'success');
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
        return;

        const url = sampleUrl();
        if (!url) {
            $testResult.addClass('is-error').text((cfg.i18n && cfg.i18n.missing_url) || 'Nhập URL truyện mẫu trước.');
            return;
        }

        const originalText = $testBtn.text();
        $testBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.test_loading) || 'Đang test selector...');
        $testResult.removeClass('is-error').text((cfg.i18n && cfg.i18n.test_loading) || 'Đang test selector...');

        try {
            const payload = Object.assign({ target_url: url }, collectSelectors());
            const response = await ajax(cfg.test_parse_action, payload);
            renderTestResult(response.data || {});
        } catch (xhr) {
            $testResult.addClass('is-error').text(errorMessage(xhr));
        } finally {
            $testBtn.prop('disabled', false).text(originalText);
        }
    });

})(jQuery);
