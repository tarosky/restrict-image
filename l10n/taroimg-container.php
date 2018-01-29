<?php
/**
 * Container l10n
 */

defined( 'ABSPATH' ) || die();

return [
	'endpoint' => \Tarosky\RestrictImage::rest_end_point( 'media', '' ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
	'loadingTxt'  => __( 'Loading...', 'taroimg' ),
	'deleteLabel' => __( 'Delete', 'taroimg' ),
	'noImageToDelete' => __( 'No image found to delete.', 'taroimg' ),
	'width'    => apply_filters( 'taroimg_thumbnail_width', get_option( 'thumbnail_size_w', 150 ) ),
];
