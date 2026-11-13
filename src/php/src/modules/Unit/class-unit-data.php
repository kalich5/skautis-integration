<?php
/**
 * Contains the Unit_Data class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Unit;

use PHPMD\Attribute\SuppressWarnings;
use PHPMD\Rule\Design\ExcessiveClassComplexity;
use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Modules\Recruitment\Recruitment_Data;
use Skautis_Integration\Utils\Helpers;

/**
 * Loads the public information about a unit from SkautIS: the seat, the published contacts, the statutory body, the bank accounts and the child units.
 *
 * Only public data are loaded: the details from the register of the units, the contacts marked as published in SkautIS and the functions from the register. They are loaded with the App ID only, without a logged-in user, and kept for a day.
 *
 * @phpstan-type Contact array{type: string, value: string, note: string}
 * @phpstan-type Person array{role: string, function: string, name: string}
 * @phpstan-type Account array{name: string, number: string}
 * @phpstan-type Child array{name: string, registration_number: string}
 * @phpstan-type UnitInfo array{id: int, name: string, registration_number: string, ic: string, address: string, lat: float, lng: float, contacts: array<Contact>, people: array<Person>, accounts: array<Account>, units: array<Child>}
 * @phpstan-type Data array{unit: UnitInfo|null, errors: array<string>, loaded: int}
 */
#[SuppressWarnings( ExcessiveClassComplexity::class )]
final class Unit_Data {

	/**
	 * The option with the registration number of the unit of the site, e.g. "112.31".
	 */
	const UNIT_OPTION = SKAUTIS_INTEGRATION_NAME . '_unit';

	/**
	 * The prefix of the transients with the loaded data, followed by the hash of the registration number.
	 */
	const CACHE_PREFIX = SKAUTIS_INTEGRATION_NAME . '_unit_data_';

	/**
	 * The role of a member of the statutory body.
	 */
	const STATUTORY = 'statutory';

	/**
	 * The role of the assistant of the statutory body.
	 */
	const ASSISTANT = 'assistant';

	/**
	 * The role of the contact person.
	 */
	const CONTACT = 'contact';

	/**
	 * The argument of FunctionAllRegistry that loads the people of a role, in the form `role => argument`.
	 */
	const ROLE_ARGUMENTS = array(
		self::STATUTORY => 'ReturnStatutory',
		self::ASSISTANT => 'ReturnAssistant',
		self::CONTACT   => 'ReturnContact',
	);

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * The errors of the SkautIS calls in this request.
	 *
	 * @var array<string>
	 */
	private $errors = array();

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway ) {
		$this->skautis_gateway = $skautis_gateway;
	}

	/**
	 * Returns the registration number of the unit of the site; without one, the unit of the recruitment.
	 */
	public static function get_unit_number(): string {
		$number = get_option( self::UNIT_OPTION, '' );
		$number = is_string( $number ) ? trim( $number ) : '';

		return '' !== $number ? $number : Recruitment_Data::get_unit_number();
	}

	/**
	 * Deletes the stored data of a unit, so that they are loaded again from SkautIS.
	 *
	 * @param string $number The registration number of the unit.
	 *
	 * @return void
	 */
	public static function clear_cache( string $number ) {
		delete_transient( self::get_cache_key( $number ) );
	}

	/**
	 * Returns the information about a unit, from the cache or from SkautIS.
	 *
	 * @param string $number The registration number of the unit, an empty string for the unit of the site.
	 *
	 * @phpstan-return Data
	 * @return array<string, mixed>
	 */
	public function get_data( string $number = '' ): array {
		$number = '' !== trim( $number ) ? trim( $number ) : self::get_unit_number();
		$cached = get_transient( self::get_cache_key( $number ) );
		if ( is_array( $cached ) && array_key_exists( 'unit', $cached ) && isset( $cached['errors'], $cached['loaded'] ) ) {
			/**
			 * The data stored by this class.
			 *
			 * @phpstan-var Data $cached
			 */
			return $cached;
		}

		$this->errors = array();
		$data         = array(
			'unit'   => '' === $number ? null : $this->load_unit( $number ),
			'errors' => array_values( array_unique( $this->errors ) ),
			'loaded' => time(),
		);
		// Without the unit, try again sooner, e.g. after the App ID is allowed to use the SkautIS methods.
		set_transient( self::get_cache_key( $number ), $data, is_null( $data['unit'] ) ? intdiv( DAY_IN_SECONDS, 24 ) : DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Returns the name of the transient with the data of a unit.
	 *
	 * The version in the hash makes the plugin load the data again when their structure changes.
	 *
	 * @param string $number The registration number of the unit.
	 */
	private static function get_cache_key( string $number ): string {
		return self::CACHE_PREFIX . md5( '2:' . $number );
	}

	/**
	 * Calls a SkautIS method of the OrganizationUnit web service, see Skautis_Gateway::call_list().
	 *
	 * @param string               $method The name of the method.
	 * @param array<string, mixed> $args The arguments of the method.
	 *
	 * @return array<\stdClass> The objects, an empty list when the call failed.
	 */
	private function call( string $method, array $args ): array {
		$response = $this->skautis_gateway->call_list( 'OrganizationUnit', $method, $args );
		if ( $this->skautis_gateway->last_call_failed() ) {
			$this->errors[] = $method;
			$this->errors   = array_values( array_unique( $this->errors ) );
		} else {
			Helpers::log_skautis_response( $method, $response );
		}

		return $response;
	}

	/**
	 * Loads the information about a unit from SkautIS.
	 *
	 * @param string $number The registration number of the unit.
	 *
	 * @phpstan-return UnitInfo|null
	 * @return array<string, mixed>|null
	 */
	private function load_unit( string $number ) {
		$unit = null;
		foreach ( $this->call( 'UnitAll', array( 'RegistrationNumber' => $number ) ) as $found ) {
			if ( self::get_string( $found, array( 'RegistrationNumber' ) ) === $number ) {
				$unit = $found;
				break;
			}
		}
		if ( is_null( $unit ) ) {
			return null;
		}
		$unit_id = self::get_int( $unit, array( 'ID' ) );
		$detail  = $this->call( 'UnitDetail', array( 'ID' => $unit_id ) )[0] ?? $unit;

		$address = self::get_address( $detail );
		$gps     = array(
			'lat' => self::get_float( $detail, array( 'GpsLatitude', 'Latitude' ) ),
			'lng' => self::get_float( $detail, array( 'GpsLongitude', 'Longitude' ) ),
		);
		if ( ( 0.0 === $gps['lat'] || 0.0 === $gps['lng'] ) && '' !== $address ) {
			$gps = Geocoder::geocode( $address );
		}

		return array(
			'id'                  => $unit_id,
			'name'                => self::get_string( $detail, array( 'DisplayName', 'FullDisplayName' ) ),
			'registration_number' => $number,
			'ic'                  => self::get_string( $detail, array( 'IC' ) ),
			'address'             => $address,
			'lat'                 => $gps['lat'],
			'lng'                 => $gps['lng'],
			'contacts'            => $this->load_contacts( $unit_id ),
			'people'              => $this->load_people( $unit_id ),
			'accounts'            => $this->load_accounts( $unit_id ),
			'units'               => $this->load_child_units( $unit_id ),
		);
	}

	/**
	 * Returns the address of the seat of a unit.
	 *
	 * @param \stdClass $detail The unit from UnitDetail.
	 */
	private static function get_address( \stdClass $detail ): string {
		$street = self::get_string( $detail, array( 'Street' ) );
		$city   = trim( self::get_string( $detail, array( 'Postcode' ) ) . ' ' . self::get_string( $detail, array( 'City' ) ) );

		return implode( ', ', array_filter( array( $street, $city ) ) );
	}

	/**
	 * Loads the contacts of a unit that are published in SkautIS.
	 *
	 * @param int $unit_id The ID of the unit.
	 *
	 * @phpstan-return array<Contact>
	 * @return array<array<string, string>>
	 */
	private function load_contacts( int $unit_id ): array {
		$result = array();
		foreach ( $this->call(
			'UnitContactAll',
			array(
				'ID_Unit' => $unit_id,
				'Publish' => true,
			)
		) as $contact ) {
			// Only the contacts marked as published, in case SkautIS returned others too.
			if ( isset( $contact->Publish ) && ! self::is_true( $contact->Publish ) ) {
				continue;
			}
			$value = self::get_string( $contact, array( 'Value' ) );
			if ( '' === $value ) {
				continue;
			}
			$result[] = array(
				'type'  => self::get_string( $contact, array( 'ContactType', 'ID_ContactType' ) ),
				'value' => $value,
				'note'  => self::get_string( $contact, array( 'Note' ) ),
			);
		}

		return $result;
	}

	/**
	 * Loads the statutory body, its assistant and the contact person of a unit from the register.
	 *
	 * Each group is loaded separately, so that the block can show them separately.
	 *
	 * @param int $unit_id The ID of the unit.
	 *
	 * @phpstan-return array<Person>
	 * @return array<array<string, string>>
	 */
	private function load_people( int $unit_id ): array {
		$result = array();
		foreach ( self::ROLE_ARGUMENTS as $role => $argument ) {
			$args = array(
				'ID_Unit'         => $unit_id,
				'ReturnStatutory' => false,
				'ReturnAssistant' => false,
				'ReturnContact'   => false,
			);

			$args[ $argument ] = true;
			foreach ( $this->call( 'FunctionAllRegistry', $args ) as $function ) {
				$name = self::get_string( $function, array( 'Person', 'PersonDisplayName', 'DisplayName' ) );
				if ( '' === $name ) {
					continue;
				}
				$result[] = array(
					'role'     => $role,
					'function' => self::get_string( $function, array( 'FunctionType', 'Function', 'DisplayName' ) ),
					'name'     => $name,
				);
			}
		}

		return $result;
	}

	/**
	 * Loads the valid bank accounts of a unit.
	 *
	 * @param int $unit_id The ID of the unit.
	 *
	 * @phpstan-return array<Account>
	 * @return array<array<string, string>>
	 */
	private function load_accounts( int $unit_id ): array {
		$result = array();
		foreach ( $this->call(
			'AccountAll',
			array(
				'ID_Unit'     => $unit_id,
				'ShowHistory' => false,
				'IsValid'     => true,
			)
		) as $account ) {
			$number = self::get_string( $account, array( 'DisplayName', 'AccountNumber' ) );
			$bank   = self::get_string( $account, array( 'BankCode', 'Bank' ) );
			if ( '' === $number ) {
				continue;
			}
			$result[] = array(
				'name'   => self::get_string( $account, array( 'Note' ) ),
				'number' => '' !== $bank && false === strpos( $number, '/' ) ? $number . '/' . $bank : $number,
			);
		}

		return $result;
	}

	/**
	 * Loads the child units of a unit, e.g. the troops of a group.
	 *
	 * @param int $unit_id The ID of the unit.
	 *
	 * @phpstan-return array<Child>
	 * @return array<array<string, string>>
	 */
	private function load_child_units( int $unit_id ): array {
		$result = array();
		foreach ( $this->call( 'UnitAll', array( 'ID_UnitParent' => $unit_id ) ) as $child ) {
			$result[] = array(
				'name'                => self::get_string( $child, array( 'DisplayName' ) ),
				'registration_number' => self::get_string( $child, array( 'RegistrationNumber' ) ),
			);
		}
		usort(
			$result,
			static function ( array $first, array $second ): int {
				return strnatcmp( $first['registration_number'], $second['registration_number'] );
			}
		);

		return $result;
	}

	/**
	 * Returns the first non-empty string property of an object.
	 *
	 * @param \stdClass     $item The object.
	 * @param array<string> $properties The names of the properties to try.
	 */
	private static function get_string( \stdClass $item, array $properties ): string {
		foreach ( $properties as $property ) {
			if ( isset( $item->$property ) && is_scalar( $item->$property ) && '' !== trim( strval( $item->$property ) ) ) {
				return trim( strval( $item->$property ) );
			}
		}

		return '';
	}

	/**
	 * Returns the first integer property of an object.
	 *
	 * @param \stdClass     $item The object.
	 * @param array<string> $properties The names of the properties to try.
	 */
	private static function get_int( \stdClass $item, array $properties ): int {
		foreach ( $properties as $property ) {
			if ( isset( $item->$property ) && is_numeric( $item->$property ) ) {
				return intval( $item->$property );
			}
		}

		return 0;
	}

	/**
	 * Returns the first decimal property of an object, e.g. a GPS coordinate.
	 *
	 * @param \stdClass     $item The object.
	 * @param array<string> $properties The names of the properties to try.
	 */
	private static function get_float( \stdClass $item, array $properties ): float {
		foreach ( $properties as $property ) {
			if ( isset( $item->$property ) && is_numeric( $item->$property ) ) {
				return floatval( $item->$property );
			}
		}

		return 0.0;
	}

	/**
	 * Checks whether a value from SkautIS is true.
	 *
	 * @param mixed $value The value.
	 */
	private static function is_true( $value ): bool {
		return true === $value || 'true' === $value || 1 === $value || '1' === $value;
	}
}
