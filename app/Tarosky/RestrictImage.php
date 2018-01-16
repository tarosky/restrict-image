<?php
namespace Tarosky;


use Hametuha\Pattern\Singleton;
use Tarosky\RestrictImage\Api\Uploader;
use Tarosky\RestrictImage\Security;

/**
 * Restrict image.
 *
 * @package taroimg
 */
class RestrictImage extends Singleton {

	const VERSION = '1.0.0';
	
	/**
	 * Prefixes
	 *
	 * @var array Array of prefix.
	 */
	private $settings = [];
	
	/**
	 * Constructor.
	 */
	protected function init() {
		// Initialize.
		Uploader::get_instance();
	}
	
	/**
	 * Check if setting is registered.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function is_registered( $key ) {
		$instance = self::get_instance();
		return isset( $instance->settings[ $key ] );
	}
	
	/**
	 * Get REST endpoint.
	 *
	 * @param string $dir Get URL.
	 *
	 * @return string
	 */
	public static function rest_end_point( $dir ) {
		return rest_url( 'restrict-image/v1/uploader/' . $dir );
	}
	
	/**
	 * Get upload directory setting.
	 *
	 * @param array  $upload_dir
	 * @param string $prefix
	 *
	 * @return array
	 */
	public static function set_directory( $upload_dir, $prefix ) {
		$upload_dir['subdir'] = '/' . $prefix . $upload_dir['subdir'];
		$upload_dir['path']   = $upload_dir['basedir'] . $upload_dir['subdir'];
		$upload_dir['url']    = $upload_dir['baseurl'] . $upload_dir['subdir'];
		/**
		 * taroimg_directory_setting
		 *
		 * @param array  $upload_dir Directory setting.
		 * @param string $prefix     Directory prefix.
		 */
		return apply_filters( 'taroimg_directory_setting', $upload_dir, $prefix );
	}
	
	/**
	 * Set updated media meta.
	 *
	 * @param int    $media_id
	 * @param string $key
	 */
	public static function update_media_meta( $media_id, $key ) {
		$setting = self::get_instance()->settings[ $key ];
		update_post_meta( $media_id, '_taroimg_key', $key );
	}
	
	/**
	 * Register new directory.
	 *
	 * @param string $dir_name              Directory name to register.
	 * @param bool   $is_restricted         If you don't have to protect, pass false.
	 * @param bool   $show_in_media_library If you want display it on media library, pass true.
	 *
	 * @return \WP_Error|bool
	 */
	public static function register( $dir_name, $is_restricted = true, $show_in_media_library = false ) {
		if ( did_action( 'init' ) ) {
			return new \WP_Error( 'invalid_register', __( 'Register directories before init hook.', 'taroimg' ) );
		}
		if ( ! Security::is_valid_dir_name( $dir_name ) ) {
			return new \WP_Error( 'invalid_dir_name', __( 'Directory name should be alphanumeric.', 'taroimg' ) );
		}
		$instance = self::get_instance();
		$instance->settings[ $dir_name ] = [
			'restricted' => (bool) $is_restricted,
			'in_library' => (bool) $show_in_media_library,
		];
		return true;
	}
}
