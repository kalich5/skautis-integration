<?php
/**
 * Contains the Login_Redirect class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Auth;

use Skautis_Integration\Services\Services;
use Skautis_Integration\Utils\Helpers;

/**
 * Chooses where a user goes after the login with SkautIS.
 *
 * A user who logs in from a page of the site returns to that page. A user who logs in on the login screen of WordPress or on the login page of the plugin, and would land on the dashboard or back on the login page, is sent to the page set on the Login tab: the page of the first redirect rule they pass, or the default page. The core `login_redirect` filter is applied too, so that the plugins and snippets that redirect users after the login work with SkautIS as well.
 */
final class Login_Redirect {

	/**
	 * The option with the default target: Login_Redirect::DASHBOARD, Login_Redirect::HOME or the ID of a page.
	 */
	const OPTION = SKAUTIS_INTEGRATION_NAME . '_login_redirect';

	/**
	 * The option with the redirect rules: a list of array{rule: int, target: int}, evaluated from top to bottom.
	 */
	const RULES_OPTION = SKAUTIS_INTEGRATION_NAME . '_login_redirect_rules';

	/**
	 * The target for the dashboard, as before.
	 */
	const DASHBOARD = 0;

	/**
	 * The target for the home page of the site.
	 */
	const HOME = -1;

	/**
	 * Returns the URL to redirect a user to after the login.
	 *
	 * @param string   $return_url The URL the user asked to return to.
	 * @param \WP_User $user The user who logged in.
	 */
	public static function get_url( string $return_url, \WP_User $user ): string {
		$redirect_to = $return_url;
		if ( self::is_login_landing( $return_url ) ) {
			$target = self::get_target_url( self::get_target() );
			if ( '' !== $target ) {
				$redirect_to = $target;
			} elseif ( false !== strpos( $return_url, 'wp-login.php' ) ) {
				// Never return back to the login screen.
				$redirect_to = admin_url();
			}
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $return_url, $user );

		/**
		 * Filters the URL where a user goes after the login with SkautIS.
		 *
		 * @param string   $redirect_to The URL.
		 * @param string   $return_url The URL the user asked to return to.
		 * @param \WP_User $user The user who logged in.
		 */
		$redirect_to = apply_filters( SKAUTIS_INTEGRATION_NAME . '_login_redirect', $redirect_to, $return_url, $user );

		return is_string( $redirect_to ) && '' !== $redirect_to ? $redirect_to : $return_url;
	}

	/**
	 * Returns whether a URL is where a user lands after logging in on a login screen: the dashboard, the login screen of WordPress or the login page of the plugin.
	 *
	 * @param string $url The URL.
	 */
	private static function is_login_landing( string $url ): bool {
		$path = strtok( $url, '?#' );
		if ( false === $path ) {
			return false;
		}
		$path       = untrailingslashit( $path );
		$login_page = Helpers::get_login_page_url();

		return untrailingslashit( admin_url() ) === $path
			|| false !== strpos( $path, 'wp-login.php' )
			|| ( '' !== $login_page && untrailingslashit( home_url( $login_page ) ) === $path );
	}

	/**
	 * Returns the target for the current user: the target of the first redirect rule they pass, or the default target.
	 */
	private static function get_target(): int {
		$rules = self::get_rules();
		if ( count( $rules ) > 0 ) {
			$rules_manager = Services::get_rules_manager();
			foreach ( $rules as $rule ) {
				if ( $rules_manager->check_if_user_passed_rules( array( $rule['rule'] ) ) ) {
					return $rule['target'];
				}
			}
		}

		return self::get_default_target();
	}

	/**
	 * Returns the default target.
	 */
	public static function get_default_target(): int {
		return intval( get_option( self::OPTION, self::DASHBOARD ) );
	}

	/**
	 * Returns the redirect rules.
	 *
	 * @return array<array{rule: int, target: int}>
	 */
	public static function get_rules(): array {
		$rules = get_option( self::RULES_OPTION, array() );

		return self::sanitize_rules( is_array( $rules ) ? $rules : array() );
	}

	/**
	 * Returns the valid redirect rules from a list, e.g. from the settings form.
	 *
	 * @param array<mixed> $rules The rules.
	 *
	 * @return array<array{rule: int, target: int}>
	 */
	public static function sanitize_rules( array $rules ): array {
		$result = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['rule'], $rule['target'] ) || ! is_numeric( $rule['rule'] ) || ! is_numeric( $rule['target'] ) ) {
				continue;
			}
			$rule_id = intval( $rule['rule'] );
			if ( $rule_id > 0 ) {
				$result[] = array(
					'rule'   => $rule_id,
					'target' => self::sanitize_target( $rule['target'] ),
				);
			}
		}

		return $result;
	}

	/**
	 * Returns a valid target.
	 *
	 * @param mixed $target The target.
	 */
	public static function sanitize_target( $target ): int {
		$target = is_numeric( $target ) ? intval( $target ) : self::DASHBOARD;

		return $target >= self::HOME ? $target : self::DASHBOARD;
	}

	/**
	 * Returns the URL of a target, or an empty string for the dashboard and for a page that is not published.
	 *
	 * @param int $target The target.
	 */
	private static function get_target_url( int $target ): string {
		if ( self::HOME === $target ) {
			return home_url( '/' );
		}
		if ( $target > 0 && 'publish' === get_post_status( $target ) ) {
			$url = get_permalink( $target );

			return false !== $url ? $url : '';
		}

		return '';
	}
}
