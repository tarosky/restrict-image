<?php
/**
 * L10n for Uploader
 */

defined( 'ABSPATH' ) || die();

return [
	'endpoint' => \Tarosky\RestrictImage::rest_end_point( 'media', '' ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
	'dropMsg'  => __( 'Drag & drop file here.', 'taroimg' ),
	'btnLabel' => __( 'Choose File', 'taroimg' ),
];
