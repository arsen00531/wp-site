<?php
/**
 * Практическая работа №4 — многостраничный сайт.
 *
 * @package Festival_PR3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FESTIVAL_PR4_OPTION_HOME', 'festival_pr4_page_home' );
define( 'FESTIVAL_PR4_OPTION_PROGRAM', 'festival_pr4_page_program' );
define( 'FESTIVAL_PR4_OPTION_SPEAKERS', 'festival_pr4_page_speakers' );
define( 'FESTIVAL_PR4_OPTION_REGISTRATION', 'festival_pr4_page_registration' );
define( 'FESTIVAL_PR4_OPTION_NAV', 'festival_pr4_navigation_id' );
define( 'FESTIVAL_PR4_OPTION_HASHES', 'festival_pr4_content_hashes' );

/**
 * Конфигурация страниц PR4.
 *
 * @return array<string, array{option: string, title: string, slug: string, file: string}>
 */
function festival_pr4_pages_config() {
	return array(
		'home'         => array(
			'option' => FESTIVAL_PR4_OPTION_HOME,
			'title'  => 'Главная',
			'slug'   => 'glavnaya',
			'file'   => 'home-blocks.html',
		),
		'program'      => array(
			'option' => FESTIVAL_PR4_OPTION_PROGRAM,
			'title'  => 'Программа',
			'slug'   => 'programma',
			'file'   => 'program-blocks.html',
		),
		'speakers'     => array(
			'option' => FESTIVAL_PR4_OPTION_SPEAKERS,
			'title'  => 'Спикеры',
			'slug'   => 'spikery',
			'file'   => 'speakers-blocks.html',
		),
		'registration' => array(
			'option' => FESTIVAL_PR4_OPTION_REGISTRATION,
			'title'  => 'Регистрация',
			'slug'   => 'registraciya',
			'file'   => 'registration-blocks.html',
		),
	);
}

/**
 * ID всех страниц фестиваля.
 *
 * @return int[]
 */
function festival_pr4_get_page_ids() {
	$ids = array();
	foreach ( festival_pr4_pages_config() as $cfg ) {
		$id = (int) get_option( $cfg['option'], 0 );
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}
	// Миграция с PR3.
	if ( empty( $ids ) ) {
		$legacy = (int) get_option( FESTIVAL_PR3_OPTION_PAGE_ID, 0 );
		if ( $legacy > 0 ) {
			$ids[] = $legacy;
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * @return int
 */
function festival_pr4_get_home_page_id() {
	$home = (int) get_option( FESTIVAL_PR4_OPTION_HOME, 0 );
	if ( $home > 0 ) {
		return $home;
	}
	return (int) get_option( FESTIVAL_PR3_OPTION_PAGE_ID, 0 );
}

/**
 * @param int $post_id Post ID.
 * @return bool
 */
function festival_pr4_is_festival_page_id( $post_id ) {
	return in_array( (int) $post_id, festival_pr4_get_page_ids(), true );
}

/**
 * @return bool
 */
function festival_pr4_is_festival_page() {
	if ( ! is_singular( 'page' ) ) {
		return false;
	}
	return festival_pr4_is_festival_page_id( (int) get_queried_object_id() );
}

/**
 * URL-замены для межстраничных ссылок.
 *
 * @return array<string, string>
 */
function festival_pr4_url_placeholders() {
	$home_id = festival_pr4_get_home_page_id();
	$program = (int) get_option( FESTIVAL_PR4_OPTION_PROGRAM, 0 );
	$speakers = (int) get_option( FESTIVAL_PR4_OPTION_SPEAKERS, 0 );
	$registration = (int) get_option( FESTIVAL_PR4_OPTION_REGISTRATION, 0 );

	return array(
		'%%HOME_URL%%'         => $home_id ? get_permalink( $home_id ) : home_url( '/' ),
		'%%PROGRAM_URL%%'      => $program ? get_permalink( $program ) : '#',
		'%%SPEAKERS_URL%%'     => $speakers ? get_permalink( $speakers ) : '#',
		'%%REGISTRATION_URL%%' => $registration ? get_permalink( $registration ) : '#',
	);
}

/**
 * @param string $filename File in content/.
 * @return string
 */
function festival_pr4_get_page_content( $filename ) {
	$file = FESTIVAL_PR3_DIR . 'content/' . $filename;
	if ( ! is_readable( $file ) ) {
		return '';
	}
	$content = file_get_contents( $file );
	$content = str_replace(
		array_keys( festival_pr4_url_placeholders() ),
		array_values( festival_pr4_url_placeholders() ),
		$content
	);
	return festival_pr3_normalize_block_content( $content );
}

/**
 * @param string $key Page key.
 * @return int
 */
function festival_pr4_upsert_page( $key ) {
	$config = festival_pr4_pages_config();
	if ( ! isset( $config[ $key ] ) ) {
		return 0;
	}

	$cfg     = $config[ $key ];
	$content = festival_pr4_get_page_content( $cfg['file'] );
	$page_id = (int) get_option( $cfg['option'], 0 );

	$post_data = array(
		'post_title'   => $cfg['title'],
		'post_name'    => $cfg['slug'],
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

	update_option( $cfg['option'], (int) $page_id );

	// Совместимость: главная = старый option PR3.
	if ( 'home' === $key ) {
		update_option( FESTIVAL_PR3_OPTION_PAGE_ID, (int) $page_id );
	}

	return (int) $page_id;
}

/**
 * Создать или обновить все 4 страницы.
 *
 * @return array<string, int>
 */
function festival_pr4_create_or_update_pages() {
	$result = array();
	foreach ( array_keys( festival_pr4_pages_config() ) as $key ) {
		$result[ $key ] = festival_pr4_upsert_page( $key );
	}
	festival_pr4_store_content_hashes();
	return $result;
}

/**
 * Сохранить хеши файлов контента.
 */
function festival_pr4_store_content_hashes() {
	$hashes = array();
	foreach ( festival_pr4_pages_config() as $key => $cfg ) {
		$file = FESTIVAL_PR3_DIR . 'content/' . $cfg['file'];
		if ( is_readable( $file ) ) {
			$hashes[ $key ] = md5_file( $file );
		}
	}
	update_option( FESTIVAL_PR4_OPTION_HASHES, $hashes );
}

/**
 * Настройки чтения и темы.
 */
function festival_pr4_configure_site() {
	$home_id = festival_pr4_get_home_page_id();
	if ( $home_id <= 0 ) {
		return;
	}

	$theme = 'twentytwentyfive';
	if ( wp_get_theme( $theme )->exists() ) {
		switch_theme( $theme );
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );

	if ( get_option( 'permalink_structure' ) === '' ) {
		update_option( 'permalink_structure', '/%postname%/' );
		flush_rewrite_rules( false );
	}
}

/**
 * Блоки меню для wp_navigation.
 *
 * @return string
 */
function festival_pr4_build_navigation_content() {
	$links = '';
	foreach ( festival_pr4_pages_config() as $key => $cfg ) {
		$page_id = (int) get_option( $cfg['option'], 0 );
		if ( $page_id <= 0 ) {
			continue;
		}
		$url   = get_permalink( $page_id );
		$label = $cfg['title'];
		$links .= sprintf(
			'<!-- wp:navigation-link {"label":"%s","type":"page","id":%d,"url":"%s","kind":"post-type"} /-->',
			esc_attr( $label ),
			$page_id,
			esc_url( $url )
		);
	}
	return $links;
}

/**
 * Создать или обновить запись wp_navigation.
 *
 * @return int
 */
function festival_pr4_create_or_update_navigation() {
	$inner   = festival_pr4_build_navigation_content();
	$content = '<!-- wp:navigation {"overlayMenu":"always","layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} -->' . $inner . '<!-- /wp:navigation -->';

	$nav_id = (int) get_option( FESTIVAL_PR4_OPTION_NAV, 0 );
	$data   = array(
		'post_title'   => 'Меню фестиваля',
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'wp_navigation',
	);

	if ( $nav_id > 0 && get_post( $nav_id ) ) {
		$data['ID'] = $nav_id;
		$nav_id     = wp_update_post( $data, true );
	} else {
		$nav_id = wp_insert_post( $data, true );
	}

	if ( is_wp_error( $nav_id ) ) {
		return 0;
	}

	update_option( FESTIVAL_PR4_OPTION_NAV, (int) $nav_id );
	return (int) $nav_id;
}

/**
 * Подставить ref меню в пустой блок Navigation в шапке.
 *
 * @param array $parsed_block Parsed block.
 * @return array
 */
function festival_pr4_inject_navigation_ref( $parsed_block ) {
	if ( ( $parsed_block['blockName'] ?? '' ) !== 'core/navigation' ) {
		return $parsed_block;
	}
	if ( ! empty( $parsed_block['attrs']['ref'] ) ) {
		return $parsed_block;
	}
	$nav_id = (int) get_option( FESTIVAL_PR4_OPTION_NAV, 0 );
	if ( $nav_id <= 0 ) {
		return $parsed_block;
	}
	if ( ! festival_pr4_is_festival_page() && ! is_front_page() ) {
		return $parsed_block;
	}
	$parsed_block['attrs']['ref'] = $nav_id;
	return $parsed_block;
}
add_filter( 'render_block_data', 'festival_pr4_inject_navigation_ref', 10, 1 );

/**
 * Полная настройка PR4.
 */
function festival_pr4_setup() {
	// Миграция: если есть только старая страница — привязать к home до пересоздания.
	$legacy = (int) get_option( FESTIVAL_PR3_OPTION_PAGE_ID, 0 );
	if ( $legacy > 0 && ! get_option( FESTIVAL_PR4_OPTION_HOME, 0 ) ) {
		update_option( FESTIVAL_PR4_OPTION_HOME, $legacy );
	}

	festival_pr4_create_or_update_pages();
	festival_pr4_configure_site();
	festival_pr4_create_or_update_navigation();
	flush_rewrite_rules();
}

/**
 * Синхронизация при изменении HTML-файлов.
 */
function festival_pr4_maybe_sync_content() {
	$stored = get_option( FESTIVAL_PR4_OPTION_HASHES, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	$changed = false;
	foreach ( festival_pr4_pages_config() as $key => $cfg ) {
		$file = FESTIVAL_PR3_DIR . 'content/' . $cfg['file'];
		if ( ! is_readable( $file ) ) {
			continue;
		}
		$hash = md5_file( $file );
		if ( ( $stored[ $key ] ?? '' ) !== $hash ) {
			$changed = true;
			break;
		}
	}

	if ( $changed || empty( festival_pr4_get_page_ids() ) || count( festival_pr4_get_page_ids() ) < 4 ) {
		festival_pr4_setup();
	}
}
add_action( 'admin_init', 'festival_pr4_maybe_sync_content', 5 );

/**
 * Нормализация при сохранении любой страницы фестиваля.
 *
 * @param string $content Content.
 * @return string
 */
function festival_pr4_normalize_on_save( $content ) {
	if ( ! is_admin() ) {
		return $content;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
	if ( $post_id <= 0 || ! festival_pr4_is_festival_page_id( $post_id ) ) {
		return $content;
	}
	return festival_pr3_normalize_block_content( $content );
}
add_filter( 'content_save_pre', 'festival_pr4_normalize_on_save', 8 );
