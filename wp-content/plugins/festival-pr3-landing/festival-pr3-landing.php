<?php
/**
 * Plugin Name: Festival PR3 Landing
 * Description: ПР №3–4 — сайт «Фестиваль цифрового искусства»: лендинг и многостраничная структура (Gutenberg).
 * Version: 2.0.0
 * Author: Student
 * Text Domain: festival-pr3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FESTIVAL_PR3_VERSION', '2.0.0' );
define( 'FESTIVAL_PR3_DIR', plugin_dir_path( __FILE__ ) );

require_once FESTIVAL_PR3_DIR . 'includes/normalize-blocks.php';
require_once FESTIVAL_PR3_DIR . 'includes/pr4-multipage.php';
define( 'FESTIVAL_PR3_OPTION_PAGE_ID', 'festival_pr3_page_id' );
define( 'FESTIVAL_PR3_OPTION_CONTENT_HASH', 'festival_pr3_content_hash' );

/**
 * ID страницы лендинга.
 *
 * @return int
 */
function festival_pr3_get_page_id() {
	return (int) get_option( FESTIVAL_PR3_OPTION_PAGE_ID, 0 );
}

/**
 * Редактируется ли сейчас страница лендинга.
 *
 * @return bool
 */
function festival_pr3_is_landing_editor_screen() {
	if ( ! is_admin() ) {
		return false;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && 'post' === $screen->base && ! empty( $GLOBALS['post']->ID ) ) {
			$post_id = (int) $GLOBALS['post']->ID;
		}
	}
	return $post_id > 0 && festival_pr4_is_festival_page_id( $post_id );
}

/**
 * Стили и шрифты лендинга.
 */
function festival_pr3_enqueue_assets() {
	if ( ! festival_pr4_is_festival_page() ) {
		return;
	}

	wp_enqueue_style(
		'festival-pr3-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=Syne:wght@600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'festival-pr3-landing',
		plugins_url( 'assets/festival.css', __FILE__ ),
		array( 'festival-pr3-fonts', 'global-styles' ),
		FESTIVAL_PR3_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'festival_pr3_enqueue_assets', 30 );

/**
 * Класс body для таргетинга CSS.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function festival_pr3_body_class( $classes ) {
	if ( festival_pr4_is_festival_page() ) {
		$classes[] = 'festival-landing';
		$classes[] = 'festival-multipage';
	}
	return $classes;
}
add_filter( 'body_class', 'festival_pr3_body_class' );

/**
 * Скрыть заголовок страницы в разметке (дубль Hero).
 *
 * @param string $block_content Content.
 * @param array  $block         Block.
 * @return string
 */
function festival_pr3_hide_page_title_block( $block_content, $block ) {
	if ( ! festival_pr4_is_festival_page() ) {
		return $block_content;
	}
	if ( isset( $block['blockName'] ) && 'core/post-title' === $block['blockName'] ) {
		return '';
	}
	return $block_content;
}
add_filter( 'render_block', 'festival_pr3_hide_page_title_block', 10, 2 );

/**
 * Те же стили, что на опубликованной странице — WYSIWYG в редакторе блоков.
 */
function festival_pr3_enqueue_block_editor_assets() {
	if ( ! festival_pr3_is_landing_editor_screen() ) {
		return;
	}

	wp_enqueue_style(
		'festival-pr3-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600&family=Syne:wght@600;700;800&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'festival-pr3-landing',
		plugins_url( 'assets/festival.css', __FILE__ ),
		array( 'festival-pr3-fonts' ),
		FESTIVAL_PR3_VERSION
	);

	wp_enqueue_style(
		'festival-pr3-editor-mirror',
		plugins_url( 'assets/festival-editor-mirror.css', __FILE__ ),
		array( 'festival-pr3-landing' ),
		FESTIVAL_PR3_VERSION
	);

	wp_enqueue_style(
		'festival-pr3-editor',
		plugins_url( 'assets/festival-editor.css', __FILE__ ),
		array( 'festival-pr3-editor-mirror' ),
		FESTIVAL_PR3_VERSION
	);
}
add_action( 'enqueue_block_editor_assets', 'festival_pr3_enqueue_block_editor_assets' );

/**
 * Подсказка в админке при редактировании страницы.
 *
 * @return void
 */
function festival_pr3_edit_screen_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'page' !== $screen->id || 'post' !== $screen->base ) {
		return;
	}
	if ( ! festival_pr3_is_landing_editor_screen() ) {
		return;
	}
	$view = get_permalink( festival_pr3_get_page_id() );
	?>
	<div class="notice notice-info">
		<p>
			<strong>Сайт фестиваля (ПР №4):</strong> в редакторе подключены те же стили, что на
			<a href="<?php echo esc_url( $view ); ?>" target="_blank" rel="noopener">опубликованной странице</a>.
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=festival-pr3' ) ); ?>">Пересоздать страницы из файлов</a>.
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'festival_pr3_edit_screen_notice' );

/**
 * Синхронизация HTML-файла → страница в БД при изменении файла.
 *
 * @return void
 */
function festival_pr3_maybe_sync_content() {
	// PR4: синхронизация в festival_pr4_maybe_sync_content (includes/pr4-multipage.php).
}
add_action( 'admin_init', 'festival_pr3_maybe_sync_content' );

/**
 * Нормализация при сохранении страницы в редакторе (без invalid content).
 *
 * @param string $content Content.
 * @return string
 */
function festival_pr3_normalize_on_save( $content ) {
	if ( ! is_admin() ) {
		return $content;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
	if ( $post_id <= 0 ) {
		return $content;
	}
	if ( ! festival_pr4_is_festival_page_id( $post_id ) ) {
		return $content;
	}

	return festival_pr3_normalize_block_content( $content );
}
add_filter( 'content_save_pre', 'festival_pr3_normalize_on_save', 10 );

/**
 * Содержимое страницы из файла блоков.
 */
function festival_pr3_get_landing_content() {
	$file = FESTIVAL_PR3_DIR . 'content/landing-blocks.html';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	return festival_pr3_normalize_block_content( file_get_contents( $file ) );
}

/**
 * Создаёт или обновляет страницу лендинга.
 *
 * @return int ID страницы.
 */
function festival_pr3_create_or_update_page() {
	$content = festival_pr3_get_landing_content();
	$page_id = (int) get_option( FESTIVAL_PR3_OPTION_PAGE_ID, 0 );

	$post_data = array(
		'post_title'   => 'Фестиваль цифрового искусства',
		'post_name'    => 'festival',
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	);

	if ( $page_id > 0 && get_post( $page_id ) ) {
		$post_data['ID'] = $page_id;
		$page_id         = wp_update_post( $post_data, true );
	} else {
		$page_id = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $page_id ) ) {
		return 0;
	}

	update_option( FESTIVAL_PR3_OPTION_PAGE_ID, $page_id );

	$file = FESTIVAL_PR3_DIR . 'content/landing-blocks.html';
	if ( is_readable( $file ) ) {
		update_option( FESTIVAL_PR3_OPTION_CONTENT_HASH, md5_file( $file ) );
	}

	return (int) $page_id;
}

/**
 * Тема Twenty Twenty-Five + главная страница = лендинг.
 */
function festival_pr3_configure_site( $page_id ) {
	if ( $page_id <= 0 ) {
		return;
	}

	$theme = 'twentytwentyfive';
	if ( wp_get_theme( $theme )->exists() ) {
		switch_theme( $theme );
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_id );

	// Одностраничник: скрываем лишние пункты меню по умолчанию (опционально).
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( empty( $locations ) ) {
		set_theme_mod( 'nav_menu_locations', array() );
	}
}

/**
 * Активация плагина.
 */
function festival_pr3_activate() {
	festival_pr4_setup();
}
register_activation_hook( __FILE__, 'festival_pr3_activate' );

/**
 * Страница в админке: пересоздать лендинг.
 */
function festival_pr3_admin_menu() {
	add_management_page(
		'Festival PR3',
		'Festival PR3',
		'manage_options',
		'festival-pr3',
		'festival_pr3_admin_page_render'
	);
}
add_action( 'admin_menu', 'festival_pr3_admin_menu' );

/**
 * @return void
 */
function festival_pr3_admin_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['festival_pr3_rebuild'] ) && check_admin_referer( 'festival_pr3_rebuild' ) ) {
		festival_pr4_setup();
		echo '<div class="notice notice-success"><p>Страницы и меню обновлены (ПР №4).</p></div>';
	}

	$home_id = festival_pr4_get_home_page_id();
	$view    = $home_id ? get_permalink( $home_id ) : home_url( '/' );
	?>
	<div class="wrap">
		<h1>Festival — ПР №3–4</h1>
		<p>Многостраничный сайт: 4 страницы + меню навигации. Контент в <code>content/*.html</code>.</p>
		<p><strong>Версия:</strong> <?php echo esc_html( FESTIVAL_PR3_VERSION ); ?> ·
			<a href="<?php echo esc_url( $view ); ?>" target="_blank" rel="noopener">Открыть главную</a> ·
			<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>">Внешний вид → Меню</a> (редактирование в админке)
		</p>
		<form method="post">
			<?php wp_nonce_field( 'festival_pr3_rebuild' ); ?>
			<p><input type="submit" name="festival_pr3_rebuild" class="button button-primary" value="Пересоздать все страницы и меню"></p>
		</form>
		<h2>Страницы (ПР №4)</h2>
		<table class="widefat striped">
			<thead><tr><th>Страница</th><th>URL</th><th>Редактировать</th></tr></thead>
			<tbody>
			<?php
			foreach ( festival_pr4_pages_config() as $key => $cfg ) :
				$pid = (int) get_option( $cfg['option'], 0 );
				if ( $pid <= 0 ) {
					continue;
				}
				?>
				<tr>
					<td><?php echo esc_html( $cfg['title'] ); ?></td>
					<td><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( get_permalink( $pid ), PHP_URL_PATH ) ); ?></a></td>
					<td><a href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>">Редактор блоков</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2>Главная (ПР №3) — секции на одной странице</h2>
		<p>Преимущества, превью спикеров, портфолио, FAQ, отзывы, контакты. Программа, спикеры и регистрация — отдельные страницы.</p>
	</div>
	<?php
}
