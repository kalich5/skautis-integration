<?php
/**
 * Contains the Skautis_Methods class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Admin;

use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Modules\Recruitment\Recruitment_Data;
use Skautis_Integration\Modules\Unit\Unit_Data;

/**
 * The SkautIS methods that the plugin calls, grouped by the features that need them, and their test for the "Status" tab.
 *
 * A method is tested by calling it. It works when SkautIS does not refuse it because the App ID of the site is not allowed to use it; an error about the data (e.g. a record that does not exist) still means that the method is allowed. Methods that change something in SkautIS are never called.
 *
 * @phpstan-type Method array{service: string, method: string, args: array<string, mixed>, call: bool}
 * @phpstan-type Group array{label: string, login: bool, methods: array<Method>}
 * @phpstan-type Result array{status: string, message: string}
 */
final class Skautis_Methods {

	/**
	 * The method works.
	 */
	const OK = 'ok';

	/**
	 * The App ID is not allowed to use the method, or the method failed.
	 */
	const ERROR = 'error';

	/**
	 * The method was not tested.
	 */
	const UNTESTED = 'untested';

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway ) {
		$this->skautis_gateway = $skautis_gateway;
	}

	/**
	 * Returns a method for a group.
	 *
	 * @param string               $service The web service.
	 * @param string               $method The method.
	 * @param array<string, mixed> $args The arguments of the test call.
	 * @param bool                 $call Whether the method may be called; methods that change something in SkautIS are not.
	 *
	 * @phpstan-return Method
	 * @return array<string, mixed>
	 */
	private static function method( string $service, string $method, array $args = array(), bool $call = true ): array {
		return array(
			'service' => $service,
			'method'  => $method,
			'args'    => $args,
			'call'    => $call,
		);
	}

	/**
	 * Returns the ID of the unit of the recruitment or of the "Unit" block, for the test calls without a logged-in user.
	 */
	private function get_unit_id(): int {
		$number = '' !== Unit_Data::get_unit_number() ? Unit_Data::get_unit_number() : Recruitment_Data::get_unit_number();
		if ( '' === $number ) {
			return 0;
		}
		$units = $this->skautis_gateway->call_list( 'OrganizationUnit', 'UnitAll', array( 'RegistrationNumber' => $number ) );

		return isset( $units[0]->ID ) ? intval( $units[0]->ID ) : 0;
	}

	/**
	 * Returns the groups of the methods called without a logged-in user, with the App ID only.
	 *
	 * @param int $unit_id The ID of the unit for the test calls.
	 *
	 * @phpstan-return array<Group>
	 * @return array<array<string, mixed>>
	 */
	private static function get_public_groups( int $unit_id ): array {
		$unit = array( 'ID_Unit' => $unit_id );

		return array(
			array(
				'label'   => __( 'Recruitment and map of the units', 'skautis-integration' ),
				'login'   => false,
				'methods' => array(
					self::method( 'OrganizationUnit', 'UnitAll', array( 'ID_UnitParent' => $unit_id ) ),
					self::method( 'OrganizationUnit', 'AdvertisingDetail', $unit ),
					self::method( 'OrganizationUnit', 'AdvertisingCategoryAll', $unit ),
					self::method( 'OrganizationUnit', 'MeetingDateAll', $unit ),
					self::method(
						'OrganizationUnit',
						'OccupationAll',
						array_merge(
							array(
								'ID_Unit'           => $unit_id,
								'IncludeChildUnits' => false,
								'Publish'           => true,
							),
							Recruitment_Data::WHOLE_MAP
						)
					),
				),
			),
			array(
				'label'   => __( 'Unit block', 'skautis-integration' ),
				'login'   => false,
				'methods' => array(
					self::method( 'OrganizationUnit', 'UnitDetail', array( 'ID' => $unit_id ) ),
					self::method( 'OrganizationUnit', 'UnitContactAll', array_merge( $unit, array( 'Publish' => true ) ) ),
					self::method(
						'OrganizationUnit',
						'FunctionAllRegistry',
						array_merge(
							$unit,
							array(
								'ReturnStatutory' => true,
								'ReturnAssistant' => false,
								'ReturnContact'   => false,
							)
						)
					),
					self::method(
						'OrganizationUnit',
						'AccountAll',
						array_merge(
							$unit,
							array(
								'ShowHistory' => false,
								'IsValid'     => true,
							)
						)
					),
				),
			),
		);
	}

	/**
	 * Returns the groups of the methods called with a logged-in user.
	 *
	 * @param int $user_id The SkautIS user ID of the logged-in user.
	 * @param int $person_id The SkautIS person ID of the logged-in user.
	 *
	 * @phpstan-return array<Group>
	 * @return array<array<string, mixed>>
	 */
	private static function get_login_groups( int $user_id, int $person_id ): array {
		$person = array( 'ID_Person' => $person_id );

		return array(
			array(
				'label'   => __( 'Login and roles', 'skautis-integration' ),
				'login'   => true,
				'methods' => array(
					self::method( 'UserManagement', 'UserDetail' ),
					self::method(
						'UserManagement',
						'UserRoleAll',
						array(
							'ID_User'  => $user_id,
							'IsActive' => true,
						)
					),
					self::method( 'UserManagement', 'RoleAll' ),
					self::method( 'UserManagement', 'LoginUpdate', array(), false ),
				),
			),
			array(
				'label'   => __( 'Registration and visibility rules', 'skautis-integration' ),
				'login'   => true,
				'methods' => array(
					self::method( 'OrganizationUnit', 'PersonDetail', array( 'ID' => $person_id ) ),
					self::method( 'OrganizationUnit', 'UnitDetail' ),
					self::method( 'OrganizationUnit', 'MembershipTypeAll' ),
					self::method(
						'OrganizationUnit',
						'MembershipAllPerson',
						array_merge(
							$person,
							array(
								'ShowHistory' => false,
								'isValid'     => true,
							)
						)
					),
					self::method( 'OrganizationUnit', 'FunctionTypeAll' ),
					self::method( 'OrganizationUnit', 'FunctionAllPerson', $person ),
					self::method( 'OrganizationUnit', 'QualificationTypeAll' ),
					self::method(
						'OrganizationUnit',
						'QualificationAll',
						array_merge(
							$person,
							array(
								'ShowHistory' => true,
								'isValid'     => true,
							)
						)
					),
					self::method( 'Events', 'EventAllPerson', $person ),
				),
			),
			array(
				'label'   => __( 'User management', 'skautis-integration' ),
				'login'   => true,
				'methods' => array(
					self::method( 'UserManagement', 'UserAll', array( 'DisplayName' => '' ) ),
					self::method( 'Events', 'EventDetail', array( 'ID' => 0 ) ),
					self::method( 'Events', 'ParticipantGeneralAll', array( 'ID_EventGeneral' => 0 ) ),
					self::method( 'Events', 'ParticipantCampAll', array( 'ID_EventCamp' => 0 ) ),
					self::method( 'Events', 'ParticipantEducationAll', array( 'ID_EventEducation' => 0 ) ),
					self::method( 'Events', 'InstructorAll', array( 'ID_EventEducation' => 0 ) ),
					self::method( 'Events', 'EventFunctionAllEducationLeader', array( 'ID_EventEducation' => 0 ) ),
					self::method( 'Events', 'EventFunctionAllCamp', array( 'ID_EventCamp' => 0 ) ),
					self::method( 'Events', 'EventFunctionAllGeneral', array( 'ID_EventGeneral' => 0 ) ),
				),
			),
			array(
				'label'   => __( 'Profile photo', 'skautis-integration' ),
				'login'   => true,
				'methods' => array(
					self::method(
						'OrganizationUnit',
						'PersonPhoto',
						array(
							'ID'   => $person_id,
							'Size' => 'small',
						)
					),
				),
			),
		);
	}

	/**
	 * Returns the result of a method that is not called: an error when SkautIS refused it before, otherwise untested.
	 *
	 * @param string $service The web service.
	 * @param string $method The method.
	 * @param string $reason Why the method is not tested.
	 *
	 * @phpstan-return Result
	 * @return array<string, string>
	 */
	private static function get_untested_result( string $service, string $method, string $reason ): array {
		if ( isset( Skautis_Gateway::get_missing_permissions()[ $service . ' → ' . $method ] ) ) {
			return array(
				'status'  => self::ERROR,
				'message' => __( 'SkautIS refused the method before: the App ID of the site is not allowed to use it.', 'skautis-integration' ),
			);
		}

		return array(
			'status'  => self::UNTESTED,
			'message' => $reason,
		);
	}

	/**
	 * Returns the important part of an error from SkautIS, without the stack trace of the server.
	 *
	 * E.g. "Nemáte oprávnění k akci EV_Event_DETAIL nad záznamem ID=0!" from a long SoapException.
	 *
	 * @param string $error The error message from SkautIS.
	 */
	private static function get_short_error( string $error ): string {
		$error = wp_strip_all_tags( $error );
		if ( 1 === preg_match( '/SqlException:\s*([^\r\n]+)/u', $error, $matches ) ) {
			$error = $matches[1];
		} else {
			$error = strtok( $error, "\r\n" );
			$error = false !== $error ? $error : '';
		}

		return mb_strlen( $error ) > 200 ? mb_substr( $error, 0, 200 ) . '…' : $error;
	}

	/**
	 * Returns the message about a missing permission, with the action of SkautIS that must be allowed for the App ID.
	 *
	 * SkautIS names the action in the error, e.g. `nemá oprávnění k anonymni akci "OU_Advertising_DETAIL_Unit"`; an anonymous action is a call without a logged-in user.
	 *
	 * @param string $error The error message from SkautIS.
	 */
	private static function get_permission_message( string $error ): string {
		if ( 1 !== preg_match( '/oprávnění k (anonymni |anonymní )?akci\W*([A-Za-z0-9_]+)/u', $error, $matches ) ) {
			return __( 'The App ID of the site is not allowed to use the method.', 'skautis-integration' );
		}

		return '' !== $matches[1]
			/* translators: %s: The name of the action in SkautIS */
			? sprintf( __( 'The App ID of the site is not allowed to use the method without a logged-in user. Ask for the anonymous action %s.', 'skautis-integration' ), $matches[2] )
			/* translators: %s: The name of the action in SkautIS */
			: sprintf( __( 'The App ID of the site is not allowed to use the method. Ask for the action %s.', 'skautis-integration' ), $matches[2] );
	}

	/**
	 * Calls a method and returns the result of the test.
	 *
	 * @phpstan-param Method $method
	 * @param array<string, mixed> $method The method.
	 *
	 * @phpstan-return Result
	 * @return array<string, string>
	 */
	private function test_method( array $method ): array {
		$this->skautis_gateway->call( $method['service'], $method['method'], $method['args'] );
		$error = $this->skautis_gateway->get_last_error();
		if ( Skautis_Gateway::is_missing_permission( $error ) ) {
			return array(
				'status'  => self::ERROR,
				'message' => self::get_permission_message( $error ),
			);
		}
		// An error of the SOAP client of PHP, e.g. a missing argument: the call did not reach SkautIS.
		if ( 0 === strpos( $error, 'SOAP-ERROR' ) ) {
			return array(
				'status'  => self::ERROR,
				/* translators: %s: The error message */
				'message' => sprintf( __( 'The plugin could not call the method: %s', 'skautis-integration' ), wp_strip_all_tags( $error ) ),
			);
		}
		Skautis_Gateway::forget_missing_permission( $method['service'], $method['method'] );
		if ( ! $this->skautis_gateway->last_call_failed() ) {
			return array(
				'status'  => self::OK,
				'message' => '',
			);
		}
		if ( '' === $error ) {
			return array(
				'status'  => self::ERROR,
				'message' => __( 'SkautIS could not be called.', 'skautis-integration' ),
			);
		}

		return array(
			'status'  => self::OK,
			/* translators: %s: The message from SkautIS */
			'message' => sprintf( __( 'The method is allowed. SkautIS answered the test call: %s', 'skautis-integration' ), self::get_short_error( $error ) ),
		);
	}

	/**
	 * Tests the methods of a group.
	 *
	 * @phpstan-param Group $group
	 * @param array<string, mixed> $group The group.
	 * @param string               $reason Why the methods are not tested, or an empty string to test them.
	 *
	 * @phpstan-return array<string, Result>
	 * @return array<string, array<string, string>> The results in the form `"Service → Method" => result`.
	 */
	private function test_group( array $group, string $reason ): array {
		$results = array();
		foreach ( $group['methods'] as $method ) {
			$key = $method['service'] . ' → ' . $method['method'];
			if ( '' !== $reason ) {
				$results[ $key ] = self::get_untested_result( $method['service'], $method['method'], $reason );
			} elseif ( true !== $method['call'] ) {
				$results[ $key ] = self::get_untested_result( $method['service'], $method['method'], __( 'Not tested, because the method changes the role of the user in SkautIS. It is used when a user switches the role.', 'skautis-integration' ) );
			} else {
				$results[ $key ] = $this->test_method( $method );
			}
		}

		return $results;
	}

	/**
	 * Tests all methods that can be tested now.
	 *
	 * The methods without a logged-in user need the registration number of a unit, the others the login of the current user to SkautIS.
	 *
	 * @param bool $logged_in Whether the current user is logged in to SkautIS.
	 *
	 * @phpstan-return array<array{group: Group, results: array<string, Result>}>
	 * @return array<array<string, mixed>>
	 */
	public function test( bool $logged_in ): array {
		$unit_id   = $this->skautis_gateway->is_initialized() ? $this->get_unit_id() : 0;
		$detail    = $logged_in ? $this->skautis_gateway->get_user_detail() : null;
		$user_id   = ! is_null( $detail ) ? intval( $detail->ID ) : 0;
		$person_id = ! is_null( $detail ) && isset( $detail->ID_Person ) ? intval( $detail->ID_Person ) : 0;

		$public_reason = '';
		if ( ! $this->skautis_gateway->is_initialized() ) {
			$public_reason = __( 'Not tested, the App ID is not set.', 'skautis-integration' );
		} elseif ( 0 === $unit_id ) {
			$public_reason = __( 'Not tested, fill in the registration number of the unit on the "Recruitment" or "Unit" page.', 'skautis-integration' );
		}
		$login_reason = '';
		if ( ! $logged_in ) {
			$login_reason = __( 'Not tested, log in to SkautIS to test the methods that need a logged-in user.', 'skautis-integration' );
		} elseif ( is_null( $detail ) ) {
			// The other methods need the user from UserDetail, which is tested below to show its error.
			$login_reason = __( 'Not tested, SkautIS did not return the logged-in user (UserManagement → UserDetail).', 'skautis-integration' );
		}

		$results = array();
		foreach ( self::get_public_groups( $unit_id ) as $group ) {
			$results[] = array(
				'group'   => $group,
				'results' => $this->test_group( $group, $public_reason ),
			);
		}
		foreach ( self::get_login_groups( $user_id, $person_id ) as $group ) {
			$tested = $this->test_group( $group, $login_reason );
			if ( $logged_in && is_null( $detail ) && isset( $tested['UserManagement → UserDetail'] ) ) {
				$tested['UserManagement → UserDetail'] = $this->test_method( self::method( 'UserManagement', 'UserDetail' ) );
			}
			$results[] = array(
				'group'   => $group,
				'results' => $tested,
			);
		}

		return $results;
	}
}
