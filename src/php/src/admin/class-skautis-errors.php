<?php
/**
 * Contains the Skautis_Errors class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Admin;

use PHPMD\Attribute\SuppressWarnings;
use PHPMD\Rule\Design\ExitExpression;
use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Utils\Helpers;

/**
 * Shows a notice in the administration when the App ID of the site is not allowed to use some SkautIS methods.
 *
 * The plugin never breaks the site when SkautIS refuses a call (see Skautis_Gateway::call()), so without this notice the administrator would only see that some data are missing.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Skautis_Errors {

	/**
	 * The admin-post action that hides the notice.
	 */
	const DISMISS_ACTION = SKAUTIS_INTEGRATION_NAME . '_dismiss_missing_permissions';

	/**
	 * Intializes all hooks used by the object.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( self::class, 'print_notice' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( self::class, 'dismiss' ) );
		// Another App ID may have other permissions.
		foreach ( array( 'skautis_integration_appid_prod', 'skautis_integration_appid_test', 'skautis_integration_appid_type' ) as $option ) {
			add_action( 'update_option_' . $option, array( Skautis_Gateway::class, 'clear_missing_permissions' ) );
		}
	}

	/**
	 * Prints the notice with the SkautIS methods that the App ID is not allowed to use.
	 *
	 * @return void
	 */
	public static function print_notice() {
		if ( ! Helpers::user_is_skautis_manager() ) {
			return;
		}
		$missing = Skautis_Gateway::get_missing_permissions();
		if ( count( $missing ) === 0 ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( 'action', self::DISMISS_ACTION, admin_url( 'admin-post.php' ) ), self::DISMISS_ACTION );
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'SkautIS refused some calls of this site.', 'skautis-integration' ) . '</strong> ';
		echo esc_html__( 'The App ID of this site is not allowed to use these SkautIS methods, so the plugin cannot show everything:', 'skautis-integration' ) . '</p><ul class="ul-disc">';
		foreach ( array_keys( $missing ) as $method ) {
			echo '<li><code>' . esc_html( $method ) . '</code></li>';
		}
		echo '</ul><p>' . esc_html__( 'Ask the SkautIS administrators to allow them for the App ID. Hide this notice after they are allowed; it shows again if SkautIS still refuses them.', 'skautis-integration' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Hide the notice', 'skautis-integration' ) . '</a></p></div>';
	}

	/**
	 * Hides the notice by forgetting the missing permissions.
	 *
	 * @return void
	 */
	#[SuppressWarnings( ExitExpression::class )]
	public static function dismiss() {
		check_admin_referer( self::DISMISS_ACTION );
		if ( ! Helpers::user_is_skautis_manager() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'skautis-integration' ) );
		}
		Skautis_Gateway::clear_missing_permissions();
		$referer = wp_get_referer();
		wp_safe_redirect( false !== $referer ? $referer : admin_url() );
		exit();
	}
}
