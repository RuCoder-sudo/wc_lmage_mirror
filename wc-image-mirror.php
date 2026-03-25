<?php
/**
 * Plugin Name: WC Image Mirror
 * Plugin URI: https://github.com/RuCoder-sudo/
 * Description: Массовое горизонтальное зеркалирование изображений товаров WooCommerce прямо из админки без изменения названий файлов.
 * Version: 3.5
 * Author: Сергей Солошенко (RuCoder)
 * Author URI: https://рукодер.рф
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-image-mirror
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * Network: false
 *
 * Разработчик: Сергей Солошенко | РуКодер
 * Специализация: Веб-разработка с 2018 года | WordPress / Full Stack
 * Принцип работы: "Сайт как для себя"
 * Контакты:
 * - Телефон/WhatsApp: +7 (985) 985-53-97
 * - Email: support@рукодер.рф
 * - Telegram: @RussCoder
 * - Портфолио: https://рукодер.рф
 * - GitHub: https://github.com/RuCoder-sudo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WCIM_VERSION', '3.5' );
define( 'WCIM_NONCE_ACTION', 'wcim_mirror_action' );
define( 'WCIM_NONCE_FIELD', 'wcim_nonce' );

class WC_Image_Mirror {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_wcim_get_images', [ $this, 'ajax_get_images' ] );
        add_action( 'wp_ajax_wcim_mirror_images', [ $this, 'ajax_mirror_images' ] );
    }

    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Зеркалирование изображений', 'wc-image-mirror' ),
            __( 'Зеркало фото', 'wc-image-mirror' ),
            'manage_woocommerce',
            'wc-image-mirror',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'woocommerce_page_wc-image-mirror' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wcim-style',
            plugin_dir_url( __FILE__ ) . 'assets/style.css',
            [],
            WCIM_VERSION
        );

        wp_enqueue_script(
            'wcim-script',
            plugin_dir_url( __FILE__ ) . 'assets/script.js',
            [ 'jquery' ],
            WCIM_VERSION,
            true
        );

        wp_localize_script( 'wcim-script', 'WCIM', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( WCIM_NONCE_ACTION ),
            'i18n'     => [
                'loading'      => __( 'Загрузка изображений...', 'wc-image-mirror' ),
                'no_images'    => __( 'Изображения не найдены.', 'wc-image-mirror' ),
                'select_least' => __( 'Выберите хотя бы одно изображение.', 'wc-image-mirror' ),
                'processing'   => __( 'Сохранение...', 'wc-image-mirror' ),
                'done'         => __( 'Готово! Изображения сохранены на сайте.', 'wc-image-mirror' ),
            ],
        ] );
    }

    public function render_page() {
        ?>
        <div class="wrap wcim-wrap">
            <h1 class="wcim-title">
                <span class="wcim-icon">🪞</span>
                <?php esc_html_e( 'Зеркалирование изображений товаров', 'wc-image-mirror' ); ?>
            </h1>

            <div class="wcim-notice wcim-notice-info">
                <strong><?php esc_html_e( 'Как это работает:', 'wc-image-mirror' ); ?></strong>
                <?php esc_html_e( 'Загрузите фото → выберите нужные → нажмите «Предпросмотр» → убедитесь что всё верно → нажмите «Применить и сохранить на сайте».', 'wc-image-mirror' ); ?>
            </div>

            <?php /* ШАГ 1 — выбор */ ?>
            <div id="wcim-step1">
                <div class="wcim-toolbar">
                    <button id="wcim-load" class="button button-primary wcim-btn-load">
                        📂 <?php esc_html_e( 'Загрузить изображения товаров', 'wc-image-mirror' ); ?>
                    </button>
                    <span id="wcim-count" class="wcim-count"></span>
                </div>

                <div id="wcim-controls" class="wcim-controls" style="display:none;">
                    <button id="wcim-select-all" class="button">
                        ✅ <?php esc_html_e( 'Выбрать все', 'wc-image-mirror' ); ?>
                    </button>
                    <button id="wcim-deselect-all" class="button">
                        ⬜ <?php esc_html_e( 'Снять выделение', 'wc-image-mirror' ); ?>
                    </button>
                    <button id="wcim-preview" class="button wcim-btn-preview">
                        👁 <?php esc_html_e( 'Предпросмотр зеркала', 'wc-image-mirror' ); ?>
                    </button>
                </div>
            </div>

            <?php /* ШАГ 2 — подтверждение */ ?>
            <div id="wcim-step2" class="wcim-step2-bar" style="display:none;">
                <div class="wcim-step2-info">
                    <strong>🪞 <?php esc_html_e( 'Предпросмотр активен', 'wc-image-mirror' ); ?></strong>
                    — <?php esc_html_e( 'Фото на карточках показаны зеркально. Если всё верно — нажмите «Применить».', 'wc-image-mirror' ); ?>
                </div>
                <div class="wcim-step2-buttons">
                    <button id="wcim-apply" class="button button-primary wcim-btn-apply">
                        ✅ <?php esc_html_e( 'Применить и сохранить на сайте', 'wc-image-mirror' ); ?>
                    </button>
                    <button id="wcim-cancel-preview" class="button wcim-btn-cancel">
                        ✖ <?php esc_html_e( 'Отмена', 'wc-image-mirror' ); ?>
                    </button>
                </div>
            </div>

            <div id="wcim-progress" class="wcim-progress" style="display:none;">
                <div class="wcim-progress-bar">
                    <div id="wcim-progress-fill" class="wcim-progress-fill"></div>
                </div>
                <span id="wcim-progress-text" class="wcim-progress-text"></span>
            </div>

            <div id="wcim-result" class="wcim-result" style="display:none;"></div>

            <div id="wcim-gallery" class="wcim-gallery"></div>
        </div>
        <?php
    }

    /**
     * AJAX: возвращает список всех изображений товаров WooCommerce
     */
    public function ajax_get_images() {
        check_ajax_referer( WCIM_NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Нет прав доступа.', 'wc-image-mirror' ) ] );
        }

        $images = [];
        $seen_ids = [];

        // Получаем все товары (включая вариации)
        $product_ids = get_posts( [
            'post_type'      => [ 'product', 'product_variation' ],
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $product_ids as $product_id ) {
            // Главное изображение
            $thumbnail_id = get_post_thumbnail_id( $product_id );
            if ( $thumbnail_id && ! in_array( $thumbnail_id, $seen_ids, true ) ) {
                $img = $this->get_image_data( $thumbnail_id );
                if ( $img ) {
                    $seen_ids[] = $thumbnail_id;
                    $images[]   = $img;
                }
            }

            // Галерея товара
            $gallery_ids = get_post_meta( $product_id, '_product_image_gallery', true );
            if ( $gallery_ids ) {
                foreach ( explode( ',', $gallery_ids ) as $gid ) {
                    $gid = (int) $gid;
                    if ( $gid && ! in_array( $gid, $seen_ids, true ) ) {
                        $img = $this->get_image_data( $gid );
                        if ( $img ) {
                            $seen_ids[] = $gid;
                            $images[]   = $img;
                        }
                    }
                }
            }
        }

        wp_send_json_success( [ 'images' => $images ] );
    }

    /**
     * AJAX: зеркалирует выбранные изображения
     */
    public function ajax_mirror_images() {
        check_ajax_referer( WCIM_NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Нет прав доступа.', 'wc-image-mirror' ) ] );
        }

        // Нужен для wp_generate_attachment_metadata()
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Снимаем ограничение времени выполнения — регенерация миниатюр может быть долгой
        @set_time_limit( 300 );

        $ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];

        if ( empty( $ids ) ) {
            wp_send_json_error( [ 'message' => __( 'Не передано ни одного ID.', 'wc-image-mirror' ) ] );
        }

        $success = 0;
        $failed  = 0;
        $errors  = [];

        foreach ( $ids as $attachment_id ) {
            $result = $this->mirror_image( $attachment_id );
            if ( $result === true ) {
                $success++;
            } else {
                $failed++;
                $errors[] = '[ID ' . $attachment_id . '] ' . $result;
            }
        }

        wp_send_json_success( [
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ] );
    }

    /**
     * Зеркалирует изображение горизонтально.
     *
     * Алгоритм:
     *  1. Читаем оригинал через GD.
     *  2. Применяем imageflip(IMG_FLIP_HORIZONTAL).
     *  3. Сохраняем во ВРЕМЕННЫЙ файл (не в оригинал напрямую).
     *  4. Атомарно переименовываем temp → оригинал.
     *  5. Удаляем ВСЕ существующие миниатюры.
     *  6. Пересоздаём миниатюры из зеркального оригинала через WordPress.
     *  7. Обновляем метаданные и сбрасываем кэши.
     *
     * @param int $attachment_id
     * @return true|string true при успехе, строка ошибки при неудаче
     */
    private function mirror_image( int $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );

        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return 'Файл не найден на диске: ' . $attachment_id;
        }

        $upload_dir = dirname( $file_path );

        if ( ! is_writable( $file_path ) || ! is_writable( $upload_dir ) ) {
            return 'Нет прав на запись: ' . basename( $file_path );
        }

        // ── 1–4. Зеркалируем оригинальный файл (GD, через temp + rename) ──
        $flip_result = $this->flip_file_gd( $file_path );
        if ( $flip_result !== true ) {
            return $flip_result;
        }

        // ── 5. Удаляем все существующие миниатюры ─────────────────────────
        $old_meta = wp_get_attachment_metadata( $attachment_id );

        if ( ! empty( $old_meta['sizes'] ) ) {
            foreach ( $old_meta['sizes'] as $size_data ) {
                if ( empty( $size_data['file'] ) ) {
                    continue;
                }
                // Миниатюры всегда в той же папке, что и оригинал
                $thumb = $upload_dir . '/' . $size_data['file'];
                if ( file_exists( $thumb ) ) {
                    @unlink( $thumb );
                }
            }
        }

        // ── 6. Пересоздаём миниатюры из зеркального оригинала ────────────
        // После того как оригинал уже зеркальный, WordPress нарежет
        // из него все нужные размеры (100x100, 150x150, 300x300 и т.д.)
        $new_meta = wp_generate_attachment_metadata( $attachment_id, $file_path );
        if ( ! empty( $new_meta ) ) {
            wp_update_attachment_metadata( $attachment_id, $new_meta );
        }

        // ── 7. Сбрасываем все кэши WordPress ─────────────────────────────
        clean_attachment_cache( $attachment_id );
        wp_cache_delete( $attachment_id, 'posts' );

        return true;
    }

    /**
     * Читает файл через GD, зеркалит горизонтально (IMG_FLIP_HORIZONTAL),
     * сохраняет результат во временный файл, затем атомарно заменяет оригинал.
     * Поддерживает jpg, jpeg, png, webp, gif.
     *
     * @param string $file_path Абсолютный путь к оригинальному файлу.
     * @return true|string true при успехе, строка с диагностикой при ошибке.
     */
    private function flip_file_gd( string $file_path ) {
        $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

        // ── Открываем изображение ─────────────────────────────────────────
        $image = null;
        switch ( $ext ) {
            case 'jpg':
            case 'jpeg':
                if ( function_exists( 'imagecreatefromjpeg' ) ) {
                    $image = @imagecreatefromjpeg( $file_path );
                }
                break;
            case 'png':
                if ( function_exists( 'imagecreatefrompng' ) ) {
                    $image = @imagecreatefrompng( $file_path );
                }
                break;
            case 'webp':
                if ( function_exists( 'imagecreatefromwebp' ) ) {
                    $image = @imagecreatefromwebp( $file_path );
                } else {
                    return 'GD без поддержки WebP. Обновите PHP/GD или установите Imagick.';
                }
                break;
            case 'gif':
                if ( function_exists( 'imagecreatefromgif' ) ) {
                    $image = @imagecreatefromgif( $file_path );
                }
                break;
            default:
                return 'Формат не поддерживается: .' . $ext;
        }

        if ( ! $image ) {
            return 'GD не смог открыть файл (битый файл или нет поддержки формата): ' . basename( $file_path );
        }

        // ── Применяем горизонтальное зеркало ─────────────────────────────
        if ( ! imageflip( $image, IMG_FLIP_HORIZONTAL ) ) {
            imagedestroy( $image );
            return 'imageflip() не удался для: ' . basename( $file_path );
        }

        // ── Создаём временный файл рядом с оригиналом ─────────────────────
        $tmp_file = $file_path . '.wcim_tmp';

        $write_ok = false;
        switch ( $ext ) {
            case 'jpg':
            case 'jpeg':
                $write_ok = imagejpeg( $image, $tmp_file, 95 );
                break;
            case 'png':
                imagesavealpha( $image, true );
                $write_ok = imagepng( $image, $tmp_file, 9 );
                break;
            case 'webp':
                $write_ok = function_exists( 'imagewebp' )
                    ? imagewebp( $image, $tmp_file, 95 )
                    : false;
                break;
            case 'gif':
                $write_ok = imagegif( $image, $tmp_file );
                break;
        }

        imagedestroy( $image );

        if ( ! $write_ok || ! file_exists( $tmp_file ) || filesize( $tmp_file ) === 0 ) {
            @unlink( $tmp_file );
            return 'Не удалось записать временный файл (нет прав или GD не поддерживает формат): ' . basename( $file_path );
        }

        // ── Атомарно заменяем оригинал временным файлом ──────────────────
        // rename() на той же файловой системе — атомарная операция
        if ( ! rename( $tmp_file, $file_path ) ) {
            @unlink( $tmp_file );
            return 'rename() не удался: нет прав или разные файловые системы: ' . basename( $file_path );
        }

        // Сбрасываем файловый кэш PHP, чтобы filesize/filemtime дали свежие данные
        clearstatcache( true, $file_path );

        return true;
    }

    /**
     * Вспомогательный метод: получает данные об изображении
     */
    private function get_image_data( int $attachment_id ): ?array {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return null;
        }

        $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ], true ) ) {
            return null;
        }

        $thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
        $full_url  = wp_get_attachment_url( $attachment_id );

        if ( ! $thumb_url || ! $full_url ) {
            return null;
        }

        return [
            'id'        => $attachment_id,
            'thumb_url' => $thumb_url,
            'full_url'  => $full_url,
            'filename'  => basename( $file_path ),
        ];
    }
}

// Инициализация только при наличии WooCommerce
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'WooCommerce' ) ) {
        new WC_Image_Mirror();
    } else {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'WC Image Mirror: требуется активный плагин WooCommerce.', 'wc-image-mirror' )
                . '</p></div>';
        } );
    }
} );
