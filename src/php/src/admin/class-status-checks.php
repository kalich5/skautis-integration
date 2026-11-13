<?php
/**
 * Contains the Status_Checks class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Admin;

use Skautis_Integration\Auth\Skautis_Gateway;

/**
 * The checks of the environment of the site on the "Status" tab: the versions of WordPress and PHP, the PHP extensions, the settings of the plugin and the connection to SkautIS.
 */
final class Status_Checks {

	/**
	 * The status of a working check.
	 */
	const OK = 'ok';

	/**
	 * The status of a check that works with limits or needs attention.
	 */
	const WARNING = 'warning';

	/**
	 * The status of a failed check.
	 */
	const ERROR = 'error';

	/**
	 * Returns a check of the environment.
	 *
	 * @param string $label What is checked.
	 * @param string $status The status: Status::OK, Status::WARNING or Status::ERROR.
	 * @param string $value The found value or the explanation.
	 *
	 * @return array{label: string, status: string, value: string}
	 */
	private static function check( string $label, string $status, string $value ): array {
		return array(
			'label'  => $label,
			'status' => $status,
			'value'  => $value,
		);
	}

	/**
	 * Returns the checks of the versions of WordPress and PHP and of the PHP extensions.
	 *
	 * @return array<array{label: string, status: string, value: string}>
	 */
	public static function get_server_checks(): array {
		global $wp_version;
		$wordpress = is_string( $wp_version ) ? $wp_version : '';
		$php       = strval( phpversion() );

		return array(
			self::check( __( 'Plugin version', 'skautis-integration' ), self::OK, SKAUTIS_INTEGRATION_VERSION ),
			self::check(
				__( 'WordPress version', 'skautis-integration' ),
				version_compare( $wordpress, '7.0', '>=' ) ? self::OK : self::ERROR,
				/* translators: %s: The version of WordPress */
				version_compare( $wordpress, '7.0', '>=' ) ? $wordpress : sprintf( __( '%s, the plugin needs WordPress 7.0 or newer', 'skautis-integration' ), $wordpress )
			),
			self::check(
				__( 'PHP version', 'skautis-integration' ),
				version_compare( $php, '8.3', '>=' ) ? self::OK : self::ERROR,
				/* translators: %s: The version of PHP */
				version_compare( $php, '8.3', '>=' ) ? $php : sprintf( __( '%s, the plugin needs PHP 8.3 or newer', 'skautis-integration' ), $php )
			),
			self::check(
				__( 'PHP extension SOAP', 'skautis-integration' ),
				extension_loaded( 'soap' ) ? self::OK : self::ERROR,
				extension_loaded( 'soap' ) ? __( 'Installed', 'skautis-integration' ) : __( 'Missing, the plugin cannot connect to SkautIS without it. Ask your hosting to turn it on.', 'skautis-integration' )
			),
			self::check(
				__( 'PHP extension OpenSSL', 'skautis-integration' ),
				extension_loaded( 'openssl' ) ? self::OK : self::ERROR,
				extension_loaded( 'openssl' ) ? __( 'Installed', 'skautis-integration' ) : __( 'Missing, the secure connection to SkautIS needs it. Ask your hosting to turn it on.', 'skautis-integration' )
			),
			self::check(
				__( 'HTTPS', 'skautis-integration' ),
				0 === strpos( home_url(), 'https://' ) ? self::OK : self::WARNING,
				0 === strpos( home_url(), 'https://' ) ? __( 'The site uses HTTPS', 'skautis-integration' ) : __( 'The site does not use HTTPS, the login data are not sent securely.', 'skautis-integration' )
			),
			self::check(
				__( 'Permalinks', 'skautis-integration' ),
				'' !== strval( get_option( 'permalink_structure', '' ) ) ? self::OK : self::ERROR,
				'' !== strval( get_option( 'permalink_structure', '' ) ) ? __( 'Pretty permalinks are on', 'skautis-integration' ) : __( 'Plain permalinks are used, the login through SkautIS needs pretty permalinks (Settings → Permalinks).', 'skautis-integration' )
			),
		);
	}

	/**
	 * Returns the checks of the settings of the plugin and of the connection to SkautIS.
	 *
	 * @param Skautis_Gateway $skautis_gateway The Skautis_Gateway service instance.
	 *
	 * @return array<array{label: string, status: string, value: string}>
	 */
	public static function get_plugin_checks( Skautis_Gateway $skautis_gateway ): array {
		$checks = array();
		$test   = Skautis_Gateway::TEST_ENV === $skautis_gateway->get_env();
		if ( ! $skautis_gateway->is_initialized() ) {
			$checks[] = self::check( __( 'App ID', 'skautis-integration' ), self::ERROR, __( 'Not set, fill in the App ID on the "General" tab.', 'skautis-integration' ) );
		} else {
			$checks[] = self::check( __( 'App ID', 'skautis-integration' ), self::OK, __( 'Set', 'skautis-integration' ) );
			$checks[] = self::check( __( 'SkautIS environment', 'skautis-integration' ), $test ? self::WARNING : self::OK, $test ? __( 'Test SkautIS (test-is.skaut.cz), only for testing', 'skautis-integration' ) : __( 'Production SkautIS (is.skaut.cz)', 'skautis-integration' ) );
			$works    = extension_loaded( 'soap' ) && $skautis_gateway->test_active_app_id();
			$checks[] = self::check( __( 'Connection to SkautIS', 'skautis-integration' ), $works ? self::OK : self::ERROR, $works ? __( 'SkautIS accepts the App ID', 'skautis-integration' ) : __( 'SkautIS did not answer or did not accept the App ID.', 'skautis-integration' ) );
		}
		$missing  = count( Skautis_Gateway::get_missing_permissions() );
		$checks[] = self::check(
			__( 'Permissions of the App ID', 'skautis-integration' ),
			0 === $missing ? self::OK : self::ERROR,
			/* translators: %d: The number of the SkautIS methods */
			0 === $missing ? __( 'SkautIS has not refused any method', 'skautis-integration' ) : sprintf( _n( 'SkautIS refused %d method, see the list below', 'SkautIS refused %d methods, see the list below', $missing, 'skautis-integration' ), $missing )
		);
		$checks[]  = self::check(
			__( 'Maps', 'skautis-integration' ),
			'' !== Maps_Settings::get_mapy_api_key() ? self::OK : self::WARNING,
			'' !== Maps_Settings::get_mapy_api_key() ? __( 'Mapy.com with the API key', 'skautis-integration' ) : __( 'OpenStreetMap, the API key of Mapy.com is not set on the "Maps" tab', 'skautis-integration' )
		);
		$debug_log = defined( 'WP_DEBUG' ) && true === WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && false !== WP_DEBUG_LOG;
		$checks[]  = self::check(
			__( 'Debug log', 'skautis-integration' ),
			$debug_log ? self::WARNING : self::OK,
			$debug_log ? __( 'On, the SkautIS responses in the log may contain personal data. Turn it off when you do not need it.', 'skautis-integration' ) : __( 'Off', 'skautis-integration' )
		);

		return $checks;
	}
}
