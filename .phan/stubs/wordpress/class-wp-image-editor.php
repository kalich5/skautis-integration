<?php
/**
 * Contains a stub of the WordPress WP_Image_Editor class for phan and PHPStan
 *
 * The skaut/wordpress-stubs package only contains the parts of WordPress that its plugins use. These stubs add the ones this plugin needs on top of it, with the signatures and types from WordPress 7.0.
 *
 * @package skautis-integration
 *
 * phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn
 * phpcs:disable Squiz.Commenting.FunctionComment.MissingParamComment
 * phpcs:disable Generic.Commenting.DocComment.MissingShort
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
 * phpcs:disable Squiz.Commenting.VariableComment.Missing
 */

declare( strict_types=1 );

/**
 * An image editor.
 */
abstract class WP_Image_Editor {

	/**
	 * @return array{width: int, height: int}
	 */
	public function get_size() {
	}

	/**
	 * @param int      $src_x
	 * @param int      $src_y
	 * @param int      $src_w
	 * @param int      $src_h
	 * @param int|null $dst_w
	 * @param int|null $dst_h
	 * @param bool     $src_abs
	 *
	 * @return true|WP_Error
	 */
	abstract public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false );

	/**
	 * @param string|null $destfilename
	 * @param string|null $mime_type
	 *
	 * @return array{path: string, file: string, width: int, height: int, mime-type: string, filesize: int|false}|WP_Error
	 */
	abstract public function save( $destfilename = null, $mime_type = null );
}
