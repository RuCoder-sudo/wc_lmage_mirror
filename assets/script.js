/* WC Image Mirror v3.5 — двухшаговый процесс */
(function ($) {
    'use strict';

    var gallery      = $('#wcim-gallery');
    var controls     = $('#wcim-controls');
    var step2bar     = $('#wcim-step2');
    var progressBox  = $('#wcim-progress');
    var progressFill = $('#wcim-progress-fill');
    var progressTxt  = $('#wcim-progress-text');
    var resultBox    = $('#wcim-result');
    var countEl      = $('#wcim-count');

    var allImages    = [];   // [{id, thumb_url, full_url, filename}, ...]
    var selected     = {};   // {id: true}
    var previewMode  = false;

    /* ═══════════════════════════════════
       ШАГ 1 — загрузка фото
    ═══════════════════════════════════ */
    $('#wcim-load').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        resultBox.hide();
        exitPreviewMode(false);
        gallery.html('<span class="wcim-spinner"></span> ' + WCIM.i18n.loading);
        controls.hide();

        $.post(WCIM.ajax_url, {
            action: 'wcim_get_images',
            nonce:  WCIM.nonce,
        }, function (resp) {
            $btn.prop('disabled', false);

            if (!resp.success) {
                gallery.html('<p style="color:red;">' + ((resp.data && resp.data.message) || 'Ошибка') + '</p>');
                return;
            }

            allImages = resp.data.images || [];
            selected  = {};

            if (!allImages.length) {
                gallery.html('<p>' + WCIM.i18n.no_images + '</p>');
                countEl.text('');
                return;
            }

            renderGallery();
            updateCount();
            controls.show();
        }).fail(function () {
            $btn.prop('disabled', false);
            gallery.html('<p style="color:red;">Ошибка соединения с сервером</p>');
        });
    });

    /* ═══════════════════════════════════
       Рендер галереи
    ═══════════════════════════════════ */
    function renderGallery() {
        gallery.empty();

        allImages.forEach(function (img) {
            var isSelected = !!selected[img.id];
            var $item = $(
                '<div class="wcim-item' + (isSelected ? ' selected' : '') + '" data-id="' + img.id + '">' +
                    '<div class="wcim-item-img-wrap">' +
                        '<div class="wcim-checkbox">' + (isSelected ? '✓' : '') + '</div>' +
                        '<span class="wcim-status-badge"></span>' +
                        '<img src="' + img.thumb_url + '" alt="' + img.filename + '" loading="lazy">' +
                    '</div>' +
                    '<div class="wcim-item-name" title="' + img.filename + '">' + img.filename + '</div>' +
                '</div>'
            );

            $item.on('click', function () {
                if (previewMode) return; // в режиме предпросмотра клики заблокированы
                toggleSelect(img.id, $item);
            });

            gallery.append($item);
        });
    }

    /* ═══════════════════════════════════
       Выбор / снятие
    ═══════════════════════════════════ */
    function toggleSelect(id, $item) {
        if (selected[id]) {
            delete selected[id];
            $item.removeClass('selected');
            $item.find('.wcim-checkbox').text('');
        } else {
            selected[id] = true;
            $item.addClass('selected');
            $item.find('.wcim-checkbox').text('✓');
        }
        updateCount();
    }

    function updateCount() {
        var cnt = Object.keys(selected).length;
        countEl.text('Найдено: ' + allImages.length + ' фото' + (cnt ? ' | Выбрано: ' + cnt : ''));
    }

    $('#wcim-select-all').on('click', function () {
        selected = {};
        allImages.forEach(function (img) { selected[img.id] = true; });
        gallery.find('.wcim-item').addClass('selected').find('.wcim-checkbox').text('✓');
        updateCount();
    });

    $('#wcim-deselect-all').on('click', function () {
        selected = {};
        gallery.find('.wcim-item').removeClass('selected').find('.wcim-checkbox').text('');
        updateCount();
    });

    /* ═══════════════════════════════════
       ШАГ 1 → Предпросмотр (CSS переворот, без записи на диск)
    ═══════════════════════════════════ */
    $('#wcim-preview').on('click', function () {
        var ids = getSelectedIds();
        if (!ids.length) {
            alert(WCIM.i18n.select_least);
            return;
        }

        // Применяем CSS зеркало к выбранным карточкам
        gallery.find('.wcim-item').each(function () {
            var id = parseInt($(this).data('id'), 10);
            if (selected[id]) {
                $(this).addClass('preview-mirror');
            }
        });

        // Переходим в режим предпросмотра
        previewMode = true;
        controls.hide();
        step2bar.show();
        resultBox.hide();

        // Прокручиваем к панели подтверждения
        $('html, body').animate({ scrollTop: step2bar.offset().top - 40 }, 300);
    });

    /* ═══════════════════════════════════
       Отмена предпросмотра
    ═══════════════════════════════════ */
    $('#wcim-cancel-preview').on('click', function () {
        exitPreviewMode(true);
    });

    function exitPreviewMode(showControls) {
        previewMode = false;
        gallery.find('.wcim-item').removeClass('preview-mirror');
        step2bar.hide();
        if (showControls && allImages.length) {
            controls.show();
        }
    }

    /* ═══════════════════════════════════
       ШАГ 2 → Применить и сохранить на сайте
    ═══════════════════════════════════ */
    $('#wcim-apply').on('click', function () {
        var ids = getSelectedIds();
        if (!ids.length) { return; }

        startMirror(ids);
    });

    function getSelectedIds() {
        return Object.keys(selected).map(Number).filter(Boolean);
    }

    function startMirror(ids) {
        // Блокируем кнопки
        step2bar.find('button').prop('disabled', true);
        resultBox.hide();

        progressBox.show();
        progressFill.css('width', '0%');
        progressTxt.text(WCIM.i18n.processing);

        var batchSize  = 5;
        var batches    = [];
        for (var i = 0; i < ids.length; i += batchSize) {
            batches.push(ids.slice(i, i + batchSize));
        }

        var totalDone  = 0;
        var totalOk    = 0;
        var totalFail  = 0;
        var allErrors  = [];
        var batchIndex = 0;

        function processBatch() {
            if (batchIndex >= batches.length) {
                onAllDone();
                return;
            }

            var batch = batches[batchIndex++];

            $.post(WCIM.ajax_url, {
                action: 'wcim_mirror_images',
                nonce:  WCIM.nonce,
                ids:    batch,
            }, function (resp) {
                if (resp.success) {
                    totalOk   += resp.data.success || 0;
                    totalFail += resp.data.failed  || 0;
                    if (resp.data.errors && resp.data.errors.length) {
                        allErrors = allErrors.concat(resp.data.errors);
                    }
                    markSaved(batch, resp.data);
                }

                totalDone += batch.length;
                var pct = Math.round((totalDone / ids.length) * 100);
                progressFill.css('width', pct + '%');
                progressTxt.text(WCIM.i18n.processing + ' ' + pct + '%');

                processBatch();
            }).fail(function () {
                totalFail += batch.length;
                totalDone += batch.length;
                processBatch();
            });
        }

        processBatch();

        /* Помечаем карточки после сохранения */
        function markSaved(batchIds, data) {
            var errors = data.errors || [];
            batchIds.forEach(function (id) {
                var $card = gallery.find('.wcim-item[data-id="' + id + '"]');
                var hasFail = errors.some(function (e) {
                    return e.indexOf('ID ' + id) !== -1;
                });

                $card.removeClass('preview-mirror'); // убираем CSS-зеркало

                if (hasFail) {
                    $card.addClass('mirrored-fail')
                         .find('.wcim-status-badge').text('❌').show();
                } else {
                    $card.addClass('mirrored-ok')
                         .find('.wcim-status-badge').text('✅').show();
                    // Обновляем превью с cache-busting — теперь покажет реально зеркальный файл
                    var $img = $card.find('img');
                    var src  = $img.attr('src').split('?')[0];
                    $img.attr('src', src + '?t=' + Date.now());
                }
            });
        }

        function onAllDone() {
            step2bar.find('button').prop('disabled', false);
            previewMode = false;
            step2bar.hide();
            controls.show();

            progressFill.css('width', '100%');
            progressTxt.text(WCIM.i18n.done);

            var msg = '✅ Сохранено на сайте: <strong>' + totalOk + ' фото</strong>';
            if (totalFail) {
                msg += '&nbsp;&nbsp;❌ Ошибок: <strong>' + totalFail + '</strong>';
                if (allErrors.length) {
                    msg += '<br><small>' + allErrors.slice(0, 5).join('<br>') + '</small>';
                }
                resultBox.attr('class', 'wcim-result error').html(msg).show();
            } else {
                resultBox.attr('class', 'wcim-result success').html(msg).show();
            }

            // Сбрасываем выделение
            selected = {};
            gallery.find('.wcim-item').removeClass('selected').find('.wcim-checkbox').text('');
            updateCount();

            setTimeout(function () { progressBox.hide(); }, 2500);
        }
    }

})(jQuery);
