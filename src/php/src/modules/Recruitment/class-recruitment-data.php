<?php
/**
 * Contains the Recruitment_Data class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment;

use PHPMD\Attribute\SuppressWarnings;
use PHPMD\Rule\Design\ExcessiveClassComplexity;
use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Utils\Helpers;

/**
 * Loads the recruitment information of a unit and its child units from SkautIS.
 *
 * The data are what the units fill in on the "Jednotka: Nábory" tab in SkautIS, the same data that are shown on the map on skaut.cz. They are loaded with the App ID of the site only, without a logged-in user, and kept for a day, because SkautIS updates them once a day anyway.
 *
 * @phpstan-type Category array{age_from: int, age_to: int, sex: string, note: string, meeting: int}
 * @phpstan-type Meeting array{id: int, day: string, day_order: int, time_from: string, time_to: string, periodicity: string, place: int, place_name: string}
 * @phpstan-type Place array{id: int, name: string, address: string, lat: float, lng: float}
 * @phpstan-type Unit array{id: int, name: string, registration_number: string, is_water: bool, note: string, categories: array<Category>, meetings: array<Meeting>, places: array<Place>}
 * @phpstan-type Data array{units: array<Unit>, errors: array<string>, loaded: int}
 */
#[SuppressWarnings( ExcessiveClassComplexity::class )]
final class Recruitment_Data {

	/**
	 * The option with the registration number of the unit of the site, e.g. "112.31".
	 */
	const UNIT_OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_unit';

	/**
	 * The option with the API key of Mapy.com.
	 */
	const MAPY_API_KEY_OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_mapy_api_key';

	/**
	 * The transient with the loaded data.
	 */
	const CACHE = SKAUTIS_INTEGRATION_NAME . '_recruitment_data';

	/**
	 * The order of the days of the week by their SkautIS ID or name.
	 */
	const DAYS = array(
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
		'sunday'    => 7,
		'pondělí'   => 1,
		'úterý'     => 2,
		'středa'    => 3,
		'čtvrtek'   => 4,
		'pátek'     => 5,
		'sobota'    => 6,
		'neděle'    => 7,
	);

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * The data loaded in this request.
	 *
	 * @phpstan-var Data|null
	 * @var array<string, mixed>|null
	 */
	private $data = null;

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
	 * Returns the registration number of the unit of the site.
	 */
	public static function get_unit_number(): string {
		$number = get_option( self::UNIT_OPTION, '' );

		return is_string( $number ) ? trim( $number ) : '';
	}

	/**
	 * Deletes the stored data, so that they are loaded again from SkautIS.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE );
	}

	/**
	 * Returns the recruitment data, from the cache or from SkautIS.
	 *
	 * @phpstan-return Data
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		if ( ! is_null( $this->data ) ) {
			return $this->data;
		}
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) && isset( $cached['units'], $cached['errors'], $cached['loaded'] ) ) {
			/**
			 * The data stored by this class.
			 *
			 * @phpstan-var Data $cached
			 */
			$this->data = $cached;
			return $this->data;
		}

		$this->data = array(
			'units'  => $this->load_units(),
			'errors' => array_values( array_unique( $this->errors ) ),
			'loaded' => time(),
		);
		// Without any unit, try again sooner, e.g. after the App ID is allowed to use the SkautIS methods.
		set_transient( self::CACHE, $this->data, count( $this->data['units'] ) > 0 ? DAY_IN_SECONDS : intdiv( DAY_IN_SECONDS, 24 ) );

		return $this->data;
	}

	/**
	 * Returns the units with recruitment information.
	 *
	 * @phpstan-return array<Unit>
	 * @return array<array<string, mixed>>
	 */
	public function get_units(): array {
		return $this->get_data()['units'];
	}

	/**
	 * Calls a SkautIS method of the OrganizationUnit web service and returns the response as a list of objects.
	 *
	 * @param string               $method The name of the method.
	 * @param array<string, mixed> $args The arguments of the method.
	 *
	 * @return array<object>|null The objects or null when the call failed.
	 */
	private function call( string $method, array $args ) {
		try {
			$response = $this->skautis_gateway->get_skautis_instance()->OrganizationUnit->$method( $args );
		} catch ( \Exception $e ) {
			Helpers::log_skautis_response( $method . ' (error)', $e->getMessage() );
			$this->errors[] = $method;
			return null;
		}
		Helpers::log_skautis_response( $method, $response );

		if ( is_object( $response ) ) {
			$response = array( $response );
		}

		return is_array( $response ) ? array_values( array_filter( $response, 'is_object' ) ) : array();
	}

	/**
	 * Loads the unit of the site, its child units and their recruitment information from SkautIS.
	 *
	 * @phpstan-return array<Unit>
	 * @return array<array<string, mixed>>
	 */
	private function load_units(): array {
		$number = self::get_unit_number();
		if ( '' === $number || ! $this->skautis_gateway->is_initialized() ) {
			return array();
		}

		$unit = null;
		foreach ( $this->call( 'UnitAll', array( 'RegistrationNumber' => $number ) ) ?? array() as $found ) {
			if ( self::get_string( $found, array( 'RegistrationNumber' ) ) === $number ) {
				$unit = $found;
				break;
			}
		}
		if ( is_null( $unit ) ) {
			$this->errors[] = 'UnitAll';
			return array();
		}

		$units    = array( $unit );
		$children = $this->call( 'UnitAll', array( 'ID_UnitParent' => self::get_int( $unit, array( 'ID' ) ) ) ) ?? array();
		usort(
			$children,
			static function ( object $first, object $second ): int {
				return strnatcmp( self::get_string( $first, array( 'RegistrationNumber' ) ), self::get_string( $second, array( 'RegistrationNumber' ) ) );
			}
		);
		$units = array_merge( $units, $children );

		$result = array();
		foreach ( $units as $found ) {
			$loaded = $this->load_unit( $found );
			if ( count( $loaded['categories'] ) > 0 || count( $loaded['meetings'] ) > 0 || '' !== $loaded['note'] ) {
				$result[] = $loaded;
			}
		}

		return $result;
	}

	/**
	 * Loads the recruitment information of a unit.
	 *
	 * @param object $unit The unit from UnitAll.
	 *
	 * @phpstan-return Unit
	 * @return array<string, mixed>
	 */
	private function load_unit( object $unit ): array {
		$unit_id     = self::get_int( $unit, array( 'ID' ) );
		$advertising = $this->call( 'AdvertisingDetail', array( 'ID_Unit' => $unit_id ) ) ?? array();
		$advertising = $advertising[0] ?? null;

		$places = array();
		foreach ( $this->call(
			'OccupationAll',
			array(
				'ID_Unit'           => $unit_id,
				'IncludeChildUnits' => false,
				'Publish'           => true,
			)
		) ?? array() as $occupation ) {
			$places[] = self::to_place( $occupation );
		}

		$meetings = array();
		foreach ( $this->call( 'MeetingDateAll', array( 'ID_Unit' => $unit_id ) ) ?? array() as $meeting ) {
			$meetings[] = self::to_meeting( $meeting );
		}
		usort(
			$meetings,
			static function ( array $first, array $second ): int {
				return array( $first['day_order'], $first['time_from'] ) <=> array( $second['day_order'], $second['time_from'] );
			}
		);

		$categories = array();
		foreach ( $this->call( 'AdvertisingCategoryAll', array( 'ID_Unit' => $unit_id ) ) ?? array() as $category ) {
			$categories[] = array(
				'age_from' => self::get_int( $category, array( 'AgeFrom' ) ),
				'age_to'   => self::get_int( $category, array( 'AgeTo' ) ),
				'sex'      => self::get_sex( $category ),
				'note'     => self::get_string( $category, array( 'Note' ) ),
				'meeting'  => self::get_int( $category, array( 'ID_MeetingDate' ) ),
			);
		}
		usort(
			$categories,
			static function ( array $first, array $second ): int {
				return array( $first['age_from'], $first['sex'] ) <=> array( $second['age_from'], $second['sex'] );
			}
		);

		return array(
			'id'                  => $unit_id,
			'name'                => self::get_string( $unit, array( 'DisplayName', 'Unit' ) ),
			'registration_number' => self::get_string( $unit, array( 'RegistrationNumber' ) ),
			'is_water'            => ! is_null( $advertising ) && self::is_true( $advertising, 'IsWater' ),
			'note'                => is_null( $advertising ) ? '' : self::get_string( $advertising, array( 'Note' ) ),
			'categories'          => $categories,
			'meetings'            => $meetings,
			'places'              => $places,
		);
	}

	/**
	 * Converts a clubroom from OccupationAll.
	 *
	 * @param object $occupation The clubroom.
	 *
	 * @phpstan-return Place
	 * @return array<string, mixed>
	 */
	private static function to_place( object $occupation ): array {
		$address = self::get_string( $occupation, array( 'Address', 'FullAddress' ) );
		if ( '' === $address ) {
			$parts   = array_filter(
				array(
					self::get_string( $occupation, array( 'Street' ) ),
					trim( self::get_string( $occupation, array( 'Postcode' ) ) . ' ' . self::get_string( $occupation, array( 'City' ) ) ),
				)
			);
			$address = implode( ', ', $parts );
		}

		return array(
			'id'      => self::get_int( $occupation, array( 'ID' ) ),
			'name'    => self::get_string( $occupation, array( 'DisplayName', 'Realty', 'RealtyDisplayName' ) ),
			'address' => $address,
			'lat'     => self::get_float( $occupation, 'GpsLatitude' ),
			'lng'     => self::get_float( $occupation, 'GpsLongitude' ),
		);
	}

	/**
	 * Converts a meeting from MeetingDateAll.
	 *
	 * @param object $meeting The meeting.
	 *
	 * @phpstan-return Meeting
	 * @return array<string, mixed>
	 */
	private static function to_meeting( object $meeting ): array {
		$day_id = self::get_string( $meeting, array( 'ID_WeekDay' ) );
		$day    = self::get_string( $meeting, array( 'WeekDay' ) );

		return array(
			'id'          => self::get_int( $meeting, array( 'ID' ) ),
			'day'         => self::upper_first( '' !== $day ? $day : $day_id ),
			'day_order'   => self::DAYS[ mb_strtolower( $day_id ) ] ?? self::DAYS[ mb_strtolower( $day ) ] ?? ( is_numeric( $day_id ) ? intval( $day_id ) : 8 ),
			'time_from'   => self::get_time( self::get_string( $meeting, array( 'TimeFrom' ) ) ),
			'time_to'     => self::get_time( self::get_string( $meeting, array( 'TimeTo' ) ) ),
			'periodicity' => self::get_string( $meeting, array( 'Periodicity' ) ),
			'place'       => self::get_int( $meeting, array( 'ID_Occupation' ) ),
			'place_name'  => self::get_string( $meeting, array( 'Occupation' ) ),
		);
	}

	/**
	 * Returns the time in the form "16:30" from the formats SkautIS may use, e.g. "16:30:00", "PT16H30M" or "1900-01-01T16:30:00".
	 *
	 * @param string $time The time from SkautIS.
	 */
	private static function get_time( string $time ): string {
		if ( 1 === preg_match( '/^PT(\d+)H(?:(\d+)M)?/u', $time, $matches ) ) {
			return intval( $matches[1] ) . ':' . str_pad( $matches[2] ?? '0', 2, '0', STR_PAD_LEFT );
		}
		if ( 1 === preg_match( '/(\d{1,2}):(\d{2})/u', $time, $matches ) ) {
			return intval( $matches[1] ) . ':' . $matches[2];
		}

		return $time;
	}

	/**
	 * Returns the text with the first letter in upper case, e.g. "Úterý" for "úterý".
	 *
	 * @param string $text The text.
	 */
	private static function upper_first( string $text ): string {
		return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
	}

	/**
	 * Returns the sex of a recruitment category: "male", "female" or "" for both.
	 *
	 * @param object $category The category from AdvertisingCategoryAll.
	 */
	private static function get_sex( object $category ): string {
		$sex = mb_strtolower( self::get_string( $category, array( 'ID_Sex' ) ) . ' ' . self::get_string( $category, array( 'Sex' ) ) );
		if ( 1 === preg_match( '/female|žen|dív|holk/u', $sex ) ) {
			return 'female';
		}
		if ( 1 === preg_match( '/male|muž|chlap|klu/u', $sex ) ) {
			return 'male';
		}

		return '';
	}

	/**
	 * Returns the first non-empty string property of an object.
	 *
	 * @param object        $item The object.
	 * @param array<string> $properties The names of the properties to try.
	 */
	private static function get_string( object $item, array $properties ): string {
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
	 * @param object        $item The object.
	 * @param array<string> $properties The names of the properties to try.
	 */
	private static function get_int( object $item, array $properties ): int {
		foreach ( $properties as $property ) {
			if ( isset( $item->$property ) && is_numeric( $item->$property ) ) {
				return intval( $item->$property );
			}
		}

		return 0;
	}

	/**
	 * Returns a decimal property of an object, e.g. a GPS coordinate.
	 *
	 * @param object $item The object.
	 * @param string $property The name of the property.
	 */
	private static function get_float( object $item, string $property ): float {
		return isset( $item->$property ) && is_numeric( $item->$property ) ? floatval( $item->$property ) : 0.0;
	}

	/**
	 * Checks whether a boolean property of an object is true.
	 *
	 * @param object $item The object.
	 * @param string $property The name of the property.
	 */
	private static function is_true( object $item, string $property ): bool {
		return isset( $item->$property ) && ( true === $item->$property || 'true' === $item->$property || 1 === $item->$property || '1' === $item->$property );
	}
}
