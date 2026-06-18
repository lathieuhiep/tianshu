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
    const $chapterStep = $('#es-template-step-chapter');

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
        return fallback || (cfg.i18n && cfg.i18n.request_failed) || 'Request failed.';
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

    function collectSelectors() {
        const data = {};
        $form.find('input[name], select[name], textarea[name]').each(function () {
            const $field = $(this);
            data[$field.attr('name')] = $field.val();
        });

        return data;
    }

    function writePreview(html) {
        const iframe = $previewFrame.get(0);
        if (!iframe || !iframe.contentDocument) {
            return;
        }

        iframe.contentDocument.open();
        iframe.contentDocument.write(html);
        iframe.contentDocument.close();
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
        html += '<strong>Story:</strong> ' + escapeHtml(data.story_title || '(empty)');
        html += '<br><strong>Author:</strong> ' + escapeHtml(data.story_author || '(empty)');
        html += '<br><strong>Chapter links:</strong> ' + escapeHtml(data.chapter_link_count || 0);
        html += warnings;
        html += '</div>';

        html += '<table class="widefat striped es-template-result-table"><tbody>';
        html += resultRow('Description', data.story_desc || '');
        html += resultRow('Thumbnail', data.story_thumb || '');
        html += resultRow('Categories', cats);
        html += resultRow('Chapter title', data.chapter_title || '');
        html += resultRow('Chapter content length', data.chapter_content_length || 0);
        html += '</tbody></table>';

        html += '<h3>Selector matches</h3>';
        html += '<table class="widefat striped es-template-result-table"><tbody>';
        Object.keys(matched).forEach(function (key) {
            html += resultRow(key, matched[key]);
        });
        html += '</tbody></table>';

        if (links.length) {
            html += '<h3>Chapter link samples</h3>';
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

    function syncExtractField($field) {
        const mode = $field.find('.es-template-extract-mode').val();
        $field.find('.es-template-direct-controls').toggleClass('is-hidden', mode === 'label');
        $field.find('.es-template-label-controls').toggleClass('is-hidden', mode !== 'label');
    }

    $('#es-template-unlock-chapter').on('click', function () {
        $chapterStep.prop('disabled', false).removeClass('is-locked');
    });

    $form.on('change', '.es-template-extract-mode', function () {
        syncExtractField($(this).closest('.es-template-extract-field'));
    });

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

    $previewBtn.on('click', async function () {
        const url = sampleUrl();
        if (!url) {
            setStatus($previewStatus, (cfg.i18n && cfg.i18n.missing_url) || 'Please enter a sample URL.', 'error');
            return;
        }

        const originalText = $previewBtn.text();
        $previewBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.loading) || 'Loading...');
        setStatus($previewStatus, '', '');

        try {
            const response = await ajax(cfg.preview_proxy_action, { target_url: url });
            writePreview((response.data && response.data.html) || '');
            setStatus($previewStatus, (cfg.i18n && cfg.i18n.preview_loaded) || 'Preview loaded.', 'success');
        } catch (xhr) {
            setStatus($previewStatus, errorMessage(xhr), 'error');
        } finally {
            $previewBtn.prop('disabled', false).text(originalText);
        }
    });

    $testBtn.on('click', async function () {
        const url = sampleUrl();
        if (!url) {
            $testResult.addClass('is-error').text((cfg.i18n && cfg.i18n.missing_url) || 'Please enter a sample URL.');
            return;
        }

        const originalText = $testBtn.text();
        $testBtn.prop('disabled', true).text((cfg.i18n && cfg.i18n.test_loading) || 'Testing selectors...');
        $testResult.removeClass('is-error').text((cfg.i18n && cfg.i18n.test_loading) || 'Testing selectors...');

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

    $(function () {
        $('.es-template-extract-field').each(function () {
            syncExtractField($(this));
        });
    });
})(jQuery);
