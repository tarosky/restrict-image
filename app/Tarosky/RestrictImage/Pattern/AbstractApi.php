<?php

namespace Tarosky\RestrictImage\Pattern;


use Hametuha\Pattern\RestApi;
use Tarosky\RestrictImage;

/**
 * Abstract API
 *
 * @package taroimg
 * @property RestrictImage\Model $model
 */
abstract class AbstractApi extends RestApi {
	
	protected $namespace = 'restrict-image';
	
	protected $version = 1;
	
	protected $route = 'media/(?P<key>[a-zA-Z0-9\-_]+)/?';
	
	/**
	 * Get arguments
	 *
	 * @param string $http_method
	 *
	 * @return array
	 */
	protected function get_args( $http_method ) {
		return [
			'key' => [
				'required'          => true,
				'validate_callback' => RestrictImage::class . '::is_registered',
			],
		];
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
	
	/**
	 * Getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'model':
				return RestrictImage\Model::get_instance();
				break;
			default:
				return null;
				break;
		}
	}
}