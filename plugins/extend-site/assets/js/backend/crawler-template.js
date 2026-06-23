(function ($) {
    'use strict';

    const cfg = window.esCrawlerTemplate || {};
    const $form = $('#es-crawler-template-form');
    const $targetUrl = $('#es-template-target-url');
    const $previewBtn = $('#es-template-load-preview');
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
    const $listToggle = $('#es-template-list-toggle');
    const $listPanel = $('#es-template-list-panel');
    const $listSearch = $('#es-template-list-search');
    const $listBody = $('#es-template-list-body');
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
        return fallback || (cfg.i18n && cfg.i18n.request_failed) || 'Request loi.';
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

        return data;
    }

    function setSaveStatus(message, type) {
        setStatus($saveStatus, message, type);
    }

    function renderTemplateOptions(templates, selectedId) {
        if (!Array.isArray(templates)) {
            return;
        }

        let html = '<option value="">Chon mau de sua</option>';
        templates.forEach(function (template) {
            const id = String(template.id || '');
            html += '<option value="' + escapeHtml(id) + '" data-domain="' + escapeHtml(template.domain || '') + '"' + (String(selectedId || '') === id ? ' selected' : '') + '>' +
                escapeHtml((template.name || '') + ' - ' + (template.domain || '')) +
                '</option>';
        });
        $templateExisting.html(html);
        renderTemplateList(templates);
    }

    function renderTemplateList(templates) {
        if (!Array.isArray(templates)) {
            return;
        }

        const rows = templates.map(function (template) {
            const id = String(template.id || '');
            const name = template.name || '';
            const domain = template.domain || '';
            const search = (name + ' ' + domain).toLowerCase();

            return '<tr data-template-id="' + escapeHtml(id) + '" data-search="' + escapeHtml(search) + '">' +
                '<td>' + escapeHtml(name) + '</td>' +
                '<td><code>' + escapeHtml(domain) + '</code></td>' +
                '<td>' +
                '<button type="button" class="button button-small es-template-list-edit" data-template-id="' + escapeHtml(id) + '">Sua</button> ' +
                '<button type="button" class="button button-small button-link-delete es-template-list-delete" data-template-id="' + escapeHtml(id) + '">Xoa</button>' +
                '</td>' +
                '</tr>';
        }).join('');

        $listBody.html(rows || '<tr><td colspan="3">Chua co mau nao.</td></tr>');
        filterTemplateList();
    }

    function filterTemplateList() {
        const query = $listSearch.val().trim().toLowerCase();
        $listBody.find('tr[data-search]').each(function () {
            const $row = $(this);
            $row.toggle(!query || String($row.data('search') || '').indexOf(query) !== -1);
        });
    }

    function setTemplateId(id) {
        const value = parseInt(id, 10) || 0;
        $templateId.val(value);
        $deleteBtn.prop('disabled', value <= 0);
        $templateExisting.val(value > 0 ? String(value) : '');
    }

    function clearForm() {
        const targetUrl = $targetUrl.val();
        $form.get(0).reset();
        $targetUrl.val(targetUrl);
        setTemplateId(0);
        $('#es-template-find-replace-rules').val('[]');
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
        $('#es-template-toc-type').val(template.toc_type || 'selector');
        $('#es-template-delay-between').val(template.delay_between || 1);
        $('#es-template-chapter-link-selector').val(template.chapter_link_selector || '');
        $('#es-template-toc-page-link-selector').val(template.toc_page_link_selector || '');
        $('#es-template-chapter-url-pattern').val(template.chapter_url_pattern || '');
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
        setSaveStatus('Da tai mau: ' + (template.name || ''), 'success');
    }

    async function loadTemplate(id) {
        id = parseInt(id, 10) || 0;
        if (!id) {
            setTemplateId(0);
            return;
        }

        setSaveStatus('Dang tai mau...', '');
        const response = await ajax(cfg.load_action, { template_id: id });
        fillTemplate((response.data && response.data.template) || {});
    }

    async function deleteTemplate(id) {
        id = parseInt(id, 10) || 0;
        if (!id || !window.confirm('Xoa mau crawler nay?')) {
            return;
        }

        const response = await ajax(cfg.delete_action, { template_id: id });
        renderTemplateOptions((response.data && response.data.templates) || [], 0);
        clearForm();
        setSaveStatus((response.data && response.data.message) || 'Da xoa mau.', 'success');
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
        const warnings = Array.isArray(data.warnings) && data.warnings.length
            ? '<ul class="es-template-warning-list">' + data.warnings.map(function (item) {
                return '<li>' + escapeHtml(item) + '</li>';
            }).join('') + '</ul>'
            : '';

        const cats = Array.isArray(data.story_cats) ? data.story_cats.join(', ') : '';
        const links = Array.isArray(data.chapter_link_samples) ? data.chapter_link_samples : [];
        const matched = data.matched || {};

        let html = '<div class="es-template-result-summary">';
        html += '<strong>Truyen:</strong> ' + escapeHtml(data.story_title || '(trong)');
        html += '<br><strong>Tac gia:</strong> ' + escapeHtml(data.story_author || '(trong)');
        html += '<br><strong>Link chuong:</strong> ' + escapeHtml(data.chapter_link_count || 0) + (data.chapter_link_estimated ? ' (uoc tinh)' : '');
        html += '<br><strong>Trang muc luc:</strong> ' + escapeHtml(data.toc_pages_scanned || 1) + '/' + escapeHtml(data.toc_page_count || 0);
        html += warnings;
        html += '</div>';

        html += '<table class="widefat striped es-template-result-table"><tbody>';
        html += resultRow('Mo ta', data.story_desc || '');
        html += resultRow('Do dai mo ta', data.story_desc_length || 0);
        html += resultRow('Anh bia', data.story_thumb || '');
        html += resultRow('The loai', cats);
        html += resultRow('Ten chuong', data.chapter_title || '');
        html += resultRow('Do dai noi dung chuong', data.chapter_content_length || 0);
        html += '</tbody></table>';

        html += '<h3>So phan tu khop selector</h3>';
        html += '<table class="widefat striped es-template-result-table"><tbody>';
        Object.keys(matched).forEach(function (key) {
            html += resultRow(key, matched[key]);
        });
        html += '</tbody></table>';

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

    function resultRow(label, value) {
        if (Array.isArray(value)) {
            value = value.join(', ');
        }

        return '<tr><th scope="row">' + escapeHtml(label) + '</th><td>' + escapeHtml(value) + '</td></tr>';
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

    $listToggle.on('click', function () {
        $listPanel.toggleClass('is-hidden');
    });

    $listSearch.on('input', filterTemplateList);

    $listBody.on('click', '.es-template-list-edit', async function () {
        try {
            await loadTemplate($(this).data('template-id'));
            $listPanel.addClass('is-hidden');
        } catch (xhr) {
            setSaveStatus(errorMessage(xhr), 'error');
        }
    });

    $listBody.on('click', '.es-template-list-delete', async function () {
        try {
            await deleteTemplate($(this).data('template-id'));
        } catch (xhr) {
            setSaveStatus(errorMessage(xhr), 'error');
        }
    });

    $saveBtn.on('click', async function () {
        const originalText = $saveBtn.text();
        $saveBtn.prop('disabled', true).text('Dang luu...');
        setSaveStatus('', '');

        try {
            const response = await ajax(cfg.save_action, collectSelectors());
            const data = response.data || {};
            renderTemplateOptions(data.templates || [], data.template && data.template.id);
            fillTemplate(data.template || {});
            setSaveStatus(data.message || 'Da luu mau.', 'success');
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
        $deleteBtn.prop('disabled', true).text('Dang xoa...');

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
            setStatus($previewStatus, (cfg.i18n && cfg.i18n.missing_url) || 'Nhap URL truyen mau truoc.', 'error');
            return;
        }

        const originalText = $previewBtn.text();
        const requestId = ++previewRequestId;
        $previewBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.loading) || 'Dang tai...');
        setStatus($previewStatus, '', '');
        clearPreview((cfg.i18n && cfg.i18n.loading) || 'Dang tai...');

        try {
            const response = await ajax(cfg.preview_proxy_action, { target_url: url, cache_buster: Date.now() });
            if (requestId !== previewRequestId || sampleUrl() !== url) {
                return;
            }

            writePreview((response.data && response.data.html) || '');
            const previewUrl = (response.data && response.data.target_url) || url;
            setStatus($previewStatus, ((cfg.i18n && cfg.i18n.preview_loaded) || 'Da tai xem truoc.') + ' ' + previewUrl, 'success');
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

    $testBtn.on('click', async function () {
        const url = sampleUrl();
        if (!url) {
            $testResult.addClass('is-error').text((cfg.i18n && cfg.i18n.missing_url) || 'Nhap URL truyen mau truoc.');
            return;
        }

        const originalText = $testBtn.text();
        $testBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.test_loading) || 'Dang test selector...');
        $testResult.removeClass('is-error').text((cfg.i18n && cfg.i18n.test_loading) || 'Dang test selector...');

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
