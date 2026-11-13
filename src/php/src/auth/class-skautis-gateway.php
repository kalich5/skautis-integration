<?php
/**
 * Contains the Skautis_Gateway class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Auth;

use Skautis_Integration\Utils\Helpers;
use Skautis_Integration\Vendor\Skautis;

/**
 * An adapter for the SkautIS library.
 */
class Skautis_Gateway {

	const PROD_ENV = 'prod';
	const TEST_ENV = 'test';

	/**
	 * The option with the SkautIS methods that the App ID of the site is not allowed to use, in the form `"Service → Method" => time of the first error`.
	 */
	const MISSING_PERMISSIONS_OPTION = SKAUTIS_INTEGRATION_NAME . '_missing_permissions';

	/**
	 * The SkautIS app ID.
	 *
	 * TODO: Private?
	 * TODO: Unused?
	 *
	 * @var string
	 */
	protected $app_id = '';

	/**
	 * An instance of the SkautIS library.
	 *
	 * @var Skautis\Skautis|null
	 */
	protected $skautis;

	/**
	 * Whether the SkautIS library instance is initialized.
	 *
	 * @var bool
	 */
	protected $skautis_initialized = false;

	/**
	 * Whether the current SkautIS environment is testing.
	 *
	 * @var bool
	 */
	protected $test_mode;

	/**
	 * The current SkautIS environment (testing or production).
	 *
	 * @var string
	 */
	protected $env;

	/**
	 * The detail of the logged-in SkautIS user, loaded once per request.
	 *
	 * @var \stdClass|null
	 */
	private $user_detail = null;

	/**
	 * Whether the last call() failed.
	 *
	 * @var bool
	 */
	private $last_call_failed = false;

	/**
	 * The error message of the last failed call, empty when the last call succeeded.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * TODO: Replace elseif with else and remove useless checks
	 */
	public function __construct() {
		$this->env = strval( get_option( 'skautis_integration_appid_type', '' ) );
		if ( self::PROD_ENV === $this->env ) {
			$this->app_id    = strval( get_option( 'skautis_integration_appid_prod', '' ) );
			$this->test_mode = false;
		} elseif ( self::TEST_ENV === $this->env ) {
			$this->app_id    = strval( get_option( 'skautis_integration_appid_test', '' ) );
			$this->test_mode = true;
		}

		if ( '' !== $this->app_id && '' !== $this->env ) {
			$session_adapter           = new Transient_Session_Adapter();
			$wsdl_manager              = new Skautis\Wsdl\WsdlManager( new Skautis\Wsdl\WebServiceFactory(), new Skautis\Config( $this->app_id, $this->test_mode ) );
			$user                      = new Skautis\User( $wsdl_manager, $session_adapter );
			$this->skautis             = new Skautis\Skautis( $wsdl_manager, $user );
			$this->skautis_initialized = true;

			if ( $this->test_mode ) {
				$this->skautis->enableDebugLog();
			}
		}
	}

	/**
	 * Returns the current SkautIS environment (testing or production).
	 */
	public function get_env(): string {
		return $this->env;
	}

	/**
	 * Returns the raw SkauIS library instance
	 */
	public function get_skautis_instance(): Skautis\Skautis {
		if ( ! ( $this->skautis instanceof Skautis\Skautis ) ) {
			wp_die( esc_html__( 'The SkautIS integration plugin cannot be used without setting a valid App ID.', 'skautis-integration' ) );
		}
		return $this->skautis;
	}

	/**
	 * Checks whether the SkautIS library instance is initialized.
	 */
	public function is_initialized(): bool {
		return $this->skautis_initialized;
	}

	/**
	 * Calls a method of a SkautIS web service without ever breaking the site.
	 *
	 * SkautIS refuses a call e.g. when the App ID of the site is not allowed to use the method, when the role of the user has no access to the data or when the login has expired. The error is written to the debug log and the call returns null, so the caller can show what it has or a message instead of a fatal error. A missing permission of the App ID is also remembered for the notice in the administration, see Admin\Skautis_Errors.
	 *
	 * @param string               $service The web service, e.g. "UserManagement".
	 * @param string               $method The method, e.g. "UserDetail".
	 * @param array<string, mixed> $args The arguments of the method.
	 *
	 * @return mixed The response, or null when the call failed.
	 */
	public function call( string $service, string $method, array $args = array() ) {
		$this->last_call_failed = true;
		$this->last_error       = '';
		if ( ! ( $this->skautis instanceof Skautis\Skautis ) ) {
			return null;
		}

		try {
			$response               = $this->skautis->$service->$method( $args );
			$this->last_call_failed = false;
			return $response;
		} catch ( \Throwable $e ) {
			$this->last_error = $e->getMessage();
			self::record_error( $service, $method, $e->getMessage() );
			return null;
		}
	}

	/**
	 * Checks whether the last call() or call_list() failed, to tell a refused call from an empty response.
	 */
	public function last_call_failed(): bool {
		return $this->last_call_failed;
	}

	/**
	 * Returns the error message of the last failed call, an empty string when the last call succeeded.
	 */
	public function get_last_error(): string {
		return $this->last_error;
	}

	/**
	 * Checks whether an error message from SkautIS means that the App ID of the site is not allowed to use the method, e.g. "Externí aplikace … nemá oprávnění k akci …".
	 *
	 * @param string $message The error message.
	 */
	public static function is_missing_permission( string $message ): bool {
		return 1 === preg_match( '/Extern\S* aplikac\S*.*nemá oprávnění/su', $message );
	}

	/**
	 * Calls a method of a SkautIS web service that returns a list, without ever breaking the site.
	 *
	 * @param string               $service The web service, e.g. "UserManagement".
	 * @param string               $method The method, e.g. "UserRoleAll".
	 * @param array<string, mixed> $args The arguments of the method.
	 *
	 * @return array<\stdClass> The items of the list, an empty list when the call failed. SkautIS returns a single item as an object, it is a list of one item here.
	 */
	public function call_list( string $service, string $method, array $args = array() ): array {
		$response = $this->call( $service, $method, $args );
		if ( is_object( $response ) ) {
			$response = array( $response );
		}

		if ( ! is_array( $response ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$response,
				static function ( $item ): bool {
					return $item instanceof \stdClass;
				}
			)
		);
	}

	/**
	 * Returns the detail of the logged-in SkautIS user (UserManagement → UserDetail), with the user ID in `ID` and the person ID in `ID_Person`.
	 *
	 * @return \stdClass|null The detail, or null when it could not be loaded.
	 */
	public function get_user_detail() {
		if ( is_null( $this->user_detail ) ) {
			$detail = $this->call( 'UserManagement', 'UserDetail' );
			if ( $detail instanceof \stdClass && isset( $detail->ID ) ) {
				$this->user_detail = $detail;
			}
		}

		return $this->user_detail;
	}

	/**
	 * Writes a refused call to the debug log and remembers a missing permission of the App ID.
	 *
	 * @param string $service The web service.
	 * @param string $method The method.
	 * @param string $message The error message from SkautIS.
	 *
	 * @return void
	 */
	private static function record_error( string $service, string $method, string $message ) {
		Helpers::log_skautis_response( $service . '->' . $method . ' (error)', $message );

		// Only a missing permission of the App ID can be fixed by the administrator, e.g. "Externí aplikace … nemá oprávnění k akci …".
		if ( ! self::is_missing_permission( $message ) ) {
			return;
		}
		$missing = self::get_missing_permissions();
		$key     = $service . ' → ' . $method;
		if ( isset( $missing[ $key ] ) ) {
			return;
		}
		$missing[ $key ] = time();
		update_option( self::MISSING_PERMISSIONS_OPTION, $missing, false );
	}

	/**
	 * Forgets a missing permission of the App ID, after the method was called successfully on the "Status" tab.
	 *
	 * @param string $service The web service.
	 * @param string $method The method.
	 *
	 * @return void
	 */
	public static function forget_missing_permission( string $service, string $method ) {
		$missing = self::get_missing_permissions();
		$key     = $service . ' → ' . $method;
		if ( isset( $missing[ $key ] ) ) {
			unset( $missing[ $key ] );
			update_option( self::MISSING_PERMISSIONS_OPTION, $missing, false );
		}
	}

	/**
	 * Returns the SkautIS methods that the App ID of the site is not allowed to use.
	 *
	 * @return array<string, int> The methods in the form `"Service → Method" => time of the first error`.
	 */
	public static function get_missing_permissions(): array {
		$missing = get_option( self::MISSING_PERMISSIONS_OPTION, array() );
		if ( ! is_array( $missing ) ) {
			return array();
		}
		$result = array();
		foreach ( $missing as $method => $time ) {
			if ( is_string( $method ) && is_numeric( $time ) ) {
				$result[ $method ] = intval( $time );
			}
		}

		return $result;
	}

	/**
	 * Forgets the missing permissions, e.g. after they were allowed in SkautIS.
	 *
	 * @return void
	 */
	public static function clear_missing_permissions() {
		delete_option( self::MISSING_PERMISSIONS_OPTION );
	}

	/**
	 * Logs the user out of SkautIS.
	 *
	 * @return void
	 */
	public function logout() {
		if ( ! is_null( $this->skautis ) ) {
			$this->skautis->setLoginData( array() );
		}
		wp_remote_get( esc_url_raw( $this->get_skautis_instance()->getLogoutUrl() ) );
	}

	/**
	 * Performs a dummy request to SkautIS to check whether the library is initialized correctly.
	 *
	 * @return bool
	 */
	public function test_active_app_id() {
		try {
			if ( isset( $this->skautis ) ) {
				if ( $this->skautis->OrganizationUnit->UnitDetail() ) {
					return true;
				}
			}
		} catch ( \Throwable $_ ) {
			// E.g. a missing SOAP extension of PHP must not break the settings.
			return false;
		}

		return false;
	}
}
