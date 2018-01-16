<?php

namespace Tarosky\RestrictImage\Api;

use Tarosky\RestrictImage;
use Tarosky\RestrictImage\Pattern\AbstractApi;

class Uploader extends AbstractApi {
	
	protected $route = 'uploader/(?P<key>[a-zA-Z0-9\-_]+)/?';
	
	/**
	 * Get arguments.
	 *
	 * @param string $http_method
	 *
	 * @return array
	 */
	protected function get_args( $http_method ) {
		return [
			'key' => [
				'required' => true,
				'validate_callback' => RestrictImage::class . '::is_registered',
			],
			'id'  => [
				'default' => 0,
				'validate_callback' => [ $this, 'is_numeric' ],
			],
			'description' => [
				'default' => '',
			],
			'file' => [
				'default' => null,
			],
		];
	}
	
	/**
	 * Handle post request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return array
	 */
	protected function handle_post( \WP_REST_Request $request ) {
		// Check file.
		$file = $this->get_file_object( $request );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		// Filter upload_dir.
		$prefix = $request->get_param( 'key' );
		add_filter( 'upload_dir', function( $upload_dir ) use ( $prefix ) {
			return RestrictImage::set_directory( $upload_dir, $prefix );
		});
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$media_id = media_handle_sideload( $file, $request->get_param( 'id' ), $request->get_param( 'description' ), [
			'post_author' => get_current_user_id(),
		] );
		if ( is_wp_error( $media_id ) ) {
			return $media_id;
		}
		// Upload success!
		RestrictImage::update_media_meta( $media_id, $prefix );
		/**
		 * taroimg_media_uploaded
		 *
		 * @param int    $attachment_id Attachment ID.
		 * @param int    $user_id       User ID.
		 * @param string $key           This images type.
		 */
		do_action( 'taroimg_media_uploaded', $media_id, get_current_user_id(), $prefix );
		return $this->map( $media_id );
	}
	
	/**
	 * File object.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return array
	 */
	protected function get_file_object( \WP_REST_Request $request ) {
		if ( isset( $_FILES['my_file'] ) ) {
			return $_FILES['my_file'];
		} else {
			// For JS.
		}
	}
	
	protected function upload_setting( $key ) {
	
	}
	
	/**
	 * Permission callback
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return bool|\WP_Error
	 */
	public function permission_callback( \WP_REST_Request $request ) {
		$can = current_user_can( 'read' );
		/**
		 * taroimg_upload_permission
		 *
		 * @param bool             $can Current user can.
		 * @param \WP_REST_Request $request Request object.
		 */
		return apply_filters( 'taroimg_upload_permission', $can, $request );
	}
}