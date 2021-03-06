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
		if ( did_action( 'plugins_loaded' ) ) {
			$this->register_i18n();
		} else {
			add_action( 'plugins_loaded', [ $this, 'register_i18n' ] );
		}
		// Filter image URL.
		add_filter( 'wp_get_attachment_image_src', [ $this, 'filter_thumbnail_url' ], 10, 2 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'filter_thumbnail_srcset' ], 10, 5 );
		// Hide from media uploader.
		add_filter( 'ajax_query_attachments_args', function( $args ) {
			$args['taroimg_is_attachment'] = true;
			return $args;
		} );
		add_filter( 'posts_where', [ $this, 'hide_from_query_attachment' ], 10, 2 );
		// Add query var.
		add_filter( 'query_vars', function( $vars ) {
			$vars[] = 'taroimg_prefix';
			$vars[] = 'taroimg_is_attachment';
			return $vars;
		} );
		// Add image URL.
		add_filter( 'rewrite_rules_array', function( $rules ) {
			return array_merge( [
				'private/media/([^/]+)/(.*)' => 'index.php?taroimg_prefix=$matches[1]&pagename=$matches[2]',
			], $rules );
		} );
		// Render Image.
		add_action( 'pre_get_posts', [ $this, 'render_image' ] );
	}
	
	/**
	 * Add translation
	 */
	public function register_i18n() {
		$mo = sprintf( 'taroimg-%s.mo', get_user_locale() );
		return load_textdomain( 'taroimg',  self::dir() . '/languages/' . $mo );
	}
	
	/**
	 * Register assets.
	 */
	public function register_assets() {
		WpEnqueueManager::register_styles( $this->dir() . '/assets/css', 'taroimg-', self::VERSION );
		WpEnqueueManager::register_js( $this->dir() . '/assets/js/components', 'taroimg-', self::VERSION );
		WpEnqueueManager::register_js_var_files( $this->dir() . '/l10n' );
		$debug = WP_DEBUG ? '' : '.min';
		$asset_base = self::asset_dir();
		wp_register_script( 'vue-js', "{$asset_base}assets/js/vue{$debug}.js", [], '2.5.13', true );
	}
	
	/**
	 * Change image URL.
	 *
	 * @param array $image
	 * @param int   $attachment_id
	 *
	 * @return mixed
	 */
	public function filter_thumbnail_url( $image, $attachment_id ) {
		if ( self::is_restricted( $attachment_id ) && false !== strpos( $image[0], '/wp-content/uploads/' ) ) {
			$url_parts = explode( '/wp-content/uploads/', $image[0] );
			$url_parts[0] = home_url( 'private/media' );
			$image[0] = implode( '/', $url_parts );
		}
		return $image;
	}

	/**
	 * Filter src set URL.
	 *
	 * @param array  $sources       Array of srcset.
	 * @param array  $size_array    Sizes.
	 * @param string $image_src     Origina src.
	 * @param array  $image_meta    Array of metadata.
	 * @param int    $attachment_id ID.
	 *
	 * @return mixed
	 */
	public function filter_thumbnail_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( self::is_restricted( $attachment_id ) ) {
			$old = home_url( 'wp-content/uploads/' );
			$new = home_url( 'private/media/' );
			foreach ( $sources as $size => $values ) {
				if ( isset( $sources[ $size ]['url'] ) ) {
					$sources[ $size ]['url'] = str_replace( $old, $new, $sources[ $size ]['url'] );
				}
			}
		}
		return $sources;
	}

	/**
	 * Hide on query attachments.
	 *
	 * @param string    $where    Where string.
	 * @param \WP_Query $wp_query Query object.
	 *
	 * @return string
	 */
	public function hide_from_query_attachment( $where, $wp_query ) {
		global $wpdb;
		if ( ! $wp_query->get( 'taroimg_is_attachment' ) ) {
			return $where;
		}
		$settings = [];
		foreach ( $this->settings as $key => $setting ) {
			if ( ! $setting['in_library'] ) {
				$settings[] = $key;
			}
		}
		if ( ! $settings ) {
			return $where;
		}
		$in = implode( ', ', array_map( function( $key ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $key );
		}, $settings ) );
		$suq_query = <<<SQL
			AND {$wpdb->posts}.ID NOT IN (
				SELECT post_id FROM {$wpdb->postmeta}
				WHERE  meta_key   = '_taroimg_key'
				AND    meta_value IN ( {$in} )
			)
SQL;
		$where .= $suq_query;
		return $where;
	}
	
	/**
	 * Render image.
	 *
	 * @param \WP_Query $wp_query
	 */
	public function render_image( &$wp_query ) {
		if ( ! $wp_query->is_main_query() ) {
			return;
		}
		$prefix = $wp_query->get( 'taroimg_prefix' );
		if ( ! $prefix || ! self::is_registered( $prefix ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			$wp_query->set_404();
			return;
		}
		nocache_headers();
		$path = $wp_query->get( 'pagename' );
		// Get attachment and check capability.
		$file = basename( $path );
		if ( preg_match( '#^(.*)-\d+x\d+(\.[a-z0-9]+)$#u', $file, $matches ) ) {
			$orig_file = $matches[1] . $matches[2];
		} else {
			$orig_file = $file;
		}
		$orig_file = rawurldecode( $orig_file );
		$guid = home_url( "private/media/{$prefix}/" . str_replace( $file, $orig_file, $path ) );
		global $wpdb;
		$query = <<<SQL
			SELECT p.* FROM {$wpdb->posts} AS p
			LEFT JOIN {$wpdb->postmeta} AS pm
			ON p.ID = pm.post_id AND pm.meta_key = '_taroimg_key'
			WHERE p.post_type = 'attachment'
			  AND p.guid = %s
			  AND pm.meta_value = %s
			LIMIT 1
SQL;
		$attachment = $wpdb->get_row( $wpdb->prepare( $query, $guid, $prefix ) );
		if ( ! $attachment ) {
			$wp_query->set_404();
			return;
		}
		// Check capability.
		if ( ! current_user_can( 'edit_others_posts' ) && get_current_user_id() !=  $attachment->post_author ) {
			$wp_query->set_404();
			return;
		}
		// Render file.
		$upload_dir = wp_upload_dir();
		$path = rawurldecode( $path );
		$real_path = "{$upload_dir['basedir']}/{$prefix}/{$path}";
		if ( ! file_exists( $real_path ) ) {
			$wp_query->set_404();
			return;
		}
		header( 'Content-Type: ' . $attachment->post_mime_type );
		readfile( $real_path );
		exit;
	}
	
	/**
	 * Detect if attachment is restricted.
	 *
	 * @param int $attchment_id
	 *
	 * @return bool
	 */
	public static function is_restricted( $attchment_id ) {
		$key = get_post_meta( $attchment_id, '_taroimg_key', true );
		return $key && self::get_setting( $key, 'restricted' );
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
		if ( RestrictImage::get_setting( $prefix, 'restricted' ) ) {
			$upload_dir['baseurl'] = home_url( "/private/media" );
		}
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
	 * Filter file URL
	 *
	 * @param array  $file_array
	 * @param string $prefix
	 *
	 * @return mixed
	 */
	public static function filter_directory( $file_array, $prefix ) {
		return $file_array;
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
		$asset_base = self::asset_dir();
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
      				},
      				methods: {
      				  alertHandler: function(message) {
      				    console.log(message);
      				    alert(message);
      				  }
      				}
    			} );
  			});
JS;
		wp_add_inline_script( 'taroimg-container', $js );
		?>
		<div id="<?= esc_attr( $id ) ?>">
			<taroimg-container directory="<?= esc_attr( $key ) ?>" :post-id="postId" :allow-upload="uploadable" @on-error="alertHandler"></taroimg-container>
		</div>
		<?php
	}

	/**
	 * Get asset directory
	 *
	 * @return string
	 */
	public static function asset_dir() {
		$dir = explode( 'app/Tarosky', __DIR__ );
		return str_replace( ABSPATH, home_url( '/' ), $dir[0] );
	}
}
