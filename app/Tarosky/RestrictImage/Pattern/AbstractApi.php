<?php

namespace Tarosky\RestrictImage\Pattern;


use Hametuha\Pattern\RestApi;

/**
 * Abstract API
 *
 * @package taroimg
 */
abstract class AbstractApi extends RestApi {
	
	protected $namespace = 'restrict-image';
	
	protected $version = 1;
	
	/**
	 * Get attachment object
	 *
	 * @param int|null|\WP_Post $attachment
	 *
	 * @return mixed
	 */
	protected function map( $attachment = null ) {
		$attachment = get_post( $attachment );
		return $attachment;
	}
}