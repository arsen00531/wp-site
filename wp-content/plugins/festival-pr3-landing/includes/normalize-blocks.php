<?php
/**
 * Лёгкая нормализация — только serialize без «ломающих» правок блоков.
 *
 * @package Festival_PR3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $content Post content.
 * @return string
 */
function festival_pr3_normalize_block_content( $content ) {
	if ( ! has_blocks( $content ) ) {
		return $content;
	}

	$blocks = parse_blocks( $content );
	$blocks = festival_pr3_fix_blocks_tree( $blocks );

	return serialize_blocks( $blocks );
}

/**
 * @param array[] $blocks Blocks.
 * @return array[]
 */
function festival_pr3_fix_blocks_tree( $blocks ) {
	$fixed = array();

	foreach ( $blocks as $block ) {
		if ( empty( $block['blockName'] ) ) {
			if ( trim( $block['innerHTML'] ?? '' ) === '' ) {
				continue;
			}
			$fixed[] = $block;
			continue;
		}

		$block = festival_pr3_fix_single_block( $block );

		if ( ! empty( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = festival_pr3_fix_blocks_tree( $block['innerBlocks'] );
		}

		$fixed[] = $block;
	}

	return $fixed;
}

/**
 * @param array $block Block.
 * @return array
 */
function festival_pr3_fix_single_block( $block ) {
	switch ( $block['blockName'] ) {
		case 'core/html':
			$block = festival_pr3_fix_html_block( $block );
			break;
		case 'core/group':
			$block = festival_pr3_fix_group_block( $block );
			break;
		case 'core/buttons':
			$block = festival_pr3_fix_buttons_block( $block );
			break;
	}

	return $block;
}

/**
 * HTML-блок: одна строка iframe (без переносов).
 *
 * @param array $block Block.
 * @return array
 */
function festival_pr3_fix_html_block( $block ) {
	$html = trim( $block['innerHTML'] ?? '' );
	$html = preg_replace( '/\s+/', ' ', $html );
	$block['innerHTML']    = $html;
	$block['innerContent'] = array( $html );

	return $block;
}

/**
 * @param array $block Block.
 * @return array
 */
function festival_pr3_fix_group_block( $block ) {
	$attrs = $block['attrs'] ?? array();

	if ( isset( $attrs['tagName'] ) && 'main' === $attrs['tagName'] ) {
		unset( $attrs['tagName'] );
	}

	$block['attrs'] = $attrs;

	foreach ( array( 'innerHTML', 'innerContent' ) as $key ) {
		if ( empty( $block[ $key ] ) ) {
			continue;
		}
		if ( is_array( $block[ $key ] ) ) {
			foreach ( $block[ $key ] as $i => $chunk ) {
				if ( is_string( $chunk ) ) {
					$block[ $key ][ $i ] = festival_pr3_replace_main_tag( $chunk );
				}
			}
		} else {
			$block[ $key ] = festival_pr3_replace_main_tag( $block[ $key ] );
		}
	}

	return $block;
}

/**
 * @param array $block Block.
 * @return array
 */
function festival_pr3_fix_buttons_block( $block ) {
	if ( ! empty( $block['innerHTML'] ) ) {
		$block['innerHTML'] = preg_replace(
			'/<div class="wp-block-buttons" style="[^"]*">/',
			'<div class="wp-block-buttons">',
			$block['innerHTML']
		);
	}

	return $block;
}

/**
 * @param string $html HTML.
 * @return string
 */
function festival_pr3_replace_main_tag( $html ) {
	return str_replace( array( '<main ', '</main>' ), array( '<div ', '</div>' ), $html );
}
