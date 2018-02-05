<?php
namespace Tarosky;


use Hametuha\Pattern\Singleton;
use Hametuha\WpEnqueueManager;
use Tarosky\RestrictImage\Api\Medias;
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
	 * Get root directory.
	 *
	 * @return string
	 */
	protected function dir() {
		return dirname( dirname( __DIR__ ) );
	}
	
	/**
	 * Constructor.
	 */
	protected function init() {
		// Initialize.
		Uploader::get_instance();
		Medias::get_instance();
		// Bulk register.
		add_action( 'init', [ $this, 'register_assets' ], 9999 );
	}
	
	/**
	 * Register assets.
	 */
	public function register_assets() {
		WpEnqueueManager::register_styles( $this->dir() . '/assets/css', 'taroimg-', self::VERSION );
		WpEnqueueManager::register_js( $this->dir() . '/assets/js/components', 'taroimg-', self::VERSION );
		WpEnqueueManager::register_js_var_files( $this->dir() . '/l10n' );
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
	 * @param string $method Method name.
	 * @param string $dir    Get URL.
	 *
	 * @return string
	 */
	public static function rest_end_point( $method, $dir = '' ) {
		return rest_url( "restrict-image/v1/{$method}/{$dir}" );
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
	 * @param string $dir_name Directory name to register.
	 * @param array  $args     If you don't have to protect, pass false.
	 *
	 * @return \WP_Error|bool
	 */
	public static function register( $dir_name, $args = [] ) {
		if ( did_action( 'init' ) ) {
			return new \WP_Error( 'invalid_register', __( 'Register directories before init hook.', 'taroimg' ) );
		}
		if ( ! Security::is_valid_dir_name( $dir_name ) ) {
			return new \WP_Error( 'invalid_dir_name', __( 'Directory name should be alphanumeric.', 'taroimg' ) );
		}
		$instance = self::get_instance();
		$arg = wp_parse_args( $args, [
			'restricted' => true,
			'in_library' => false,
			'limit' => 1,
		] );
		$arg['limit'] = (int) $arg['limit'];
		$instance->settings[ $dir_name ] = $arg;
		return true;
	}
	
	/**
	 * Get setting.
	 *
	 * @param string $key
	 * @param string $name
	 *
	 * @return mixed
	 */
	public static function get_setting( $key, $name ) {
		if ( ! self::is_registered( $key ) ) {
			return null;
		} else {
			$setting = self::get_instance()->settings[ $key ];
			return isset( $setting[ $name ] ) ? $setting[ $name ] : null;
		}
	}
	
	/**
	 * Render form
	 *
	 * @param string $key        Key name.
	 * @param int    $post_id    Post ID to attach.
	 * @param bool   $uploadalbe Uploadable.
	 *
	 * @return string
	 */
	public static function form( $key, $post_id = 0, $uploadalbe = true ) {
		if ( ! self::is_registered( $key ) ) {
			return ;
		}
		$url   = self::rest_end_point( 'media', $key );
		$dir = explode( 'app/Tarosky', __DIR__ );
		$asset_base = str_replace( ABSPATH, home_url( '/' ), $dir[0] );
		$debug = WP_DEBUG ? '' : '.min';
		wp_enqueue_script( 'vue-js', "{$asset_base}assets/js/vue{$debug}.js", [], '2.5.13', true );
		wp_enqueue_script( 'taroimg-container' );
		wp_enqueue_style( 'taroimg-uploader' );
		$id = 'taro-img-container-' . $key;
		$post_id = (int) $post_id;
		$uploadalbe = $uploadalbe ? 'true' : 'false';
		$js = <<<JS
			jQuery(document).ready(function(){
    			var app = new Vue( {
      				el: '#{$id}',
      				data: {
      				  postId: {$post_id},
      				  uploadable: {$uploadalbe}
      				}
    			} );
  			});
JS;
		wp_add_inline_script( 'taroimg-container', $js );
		?>
		<div id="<?= esc_attr( $id ) ?>">
			<taroimg-container directory="<?= esc_attr( $key ) ?>" :post-id="postId" :allow-upload="uploadable"></taroimg-container>
		</div>
		<?php
	}
}
