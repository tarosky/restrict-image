<?php
namespace Tarosky\RestrictImage;

/**
 * Security class.
 */
final class Security {
	
	/**
	 * Detect if
	 *
	 * @param string $dir Directory name.
	 *
	 * @return bool
	 */
	public static function is_valid_dir_name( $dir ) {
		return (bool) preg_match( '#^[a-zA-Z0-9\-_]+$#u', $dir );
	}
	
}
