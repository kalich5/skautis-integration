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
 * Loads the recruitment information of a unit and all units below it from SkautIS, e.g. of a district (okres), its groups (střediska) and their troops (oddíly).
 *
 * The data are what the units fill in on the "Jednotka: Nábory" tab in SkautIS, the same data that are shown on the map on skaut.cz. They are loaded with the App ID of the site only, without a logged-in user, and kept for a day, because SkautIS updates them once a day anyway.
 *
 * @phpstan-type Category array{age_from: int, age_to: int, sex: string, note: string, meeting: int}
 * @phpstan-type Meeting array{id: int, day: string, day_order: int, time_from: string, time_to: string, periodicity: string, place: int, place_name: string}
 * @phpstan-type Place array{id: int, name: string, address: string, lat: float, lng: float}
 * @phpstan-type Unit array{id: int, name: string, registration_number: string, is_water: bool, note: string, categories: array<Category>, meetings: array<Meeting>, places: array<Place>, group_id: int, group_name: string}
 * @phpstan-type Data array{units: array<Unit>, errors: array<string>, loaded: int, truncated: bool}
 * @phpstan-type Node array{id: int, name: string, number: string, parent_id: int, parent_name: string, depth: int}
 * @phpstan-type State array{nodes: array<Node>|null, next: int, units: array<Unit>, errors: array<string>, tree_truncated: bool, complete: bool}
 */
#[SuppressWarnings( ExcessiveClassComplexity::class )]
final class Recruitment_Data {

	/**
	 * The option with the registration number of the unit of the site, e.g. "112.31".
	 */
	const UNIT_OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_unit';

	/**
	 * The prefix of the options with the loaded data, followed by the hash of the registration number. The transient of this name holds the data of the versions before.
	 */
	const CACHE = SKAUTIS_INTEGRATION_NAME . '_recruitment_data';

	/**
	 * The most seconds spent loading the units in one run in the background.
	 */
	const TIME_LIMIT = 20;

	/**
	 * The most seconds spent loading the units when a visitor or a manager opens a page and nothing is stored yet; the rest is loaded in the background.
	 */
	const FIRST_TIME_LIMIT = 8;

	/**
	 * The WP-Cron hook that loads the data of a unit in the background.
	 */
	const REFRESH_HOOK = SKAUTIS_INTEGRATION_NAME . '_recruitment_refresh';

	/**
	 * The option with the registration numbers of the units whose data are stored.
	 */
	const CACHED_NUMBERS_OPTION = SKAUTIS_INTEGRATION_NAME . '_recruitment_cached_units';

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
	 * The area of the map for OccupationAll, which requires one. The whole world, so that no clubroom is left out.
	 */
	const WHOLE_MAP = array(
		'GpsLatitudeStart'  => -90,
		'GpsLongitudeStart' => -180,
		'GpsLatitudeEnd'    => 90,
		'GpsLongitudeEnd'   => 180,
	);

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * The data loaded in this request, by the registration number.
	 *
	 * @phpstan-var array<string, Data>
	 * @var array<string, array<string, mixed>>
	 */
	private $data = array();

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
	 * Returns a registration number from the user, e.g. from the settings of a block, without other characters.
	 *
	 * @param mixed $number The registration number.
	 */
	public static function sanitize_number( $number ): string {
		return is_string( $number ) ? (string) preg_replace( '/[^0-9.\-]/u', '', $number ) : '';
	}

	/**
	 * Deletes the stored data of all units, so that they are loaded again from SkautIS.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		$numbers = get_option( self::CACHED_NUMBERS_OPTION, array() );
		foreach ( is_array( $numbers ) ? $numbers : array() as $number ) {
			delete_option( self::get_store_key( strval( $number ) ) );
		}
		delete_option( self::CACHED_NUMBERS_OPTION );
		// The transient of the versions before the data were stored per unit.
		delete_transient( self::CACHE );
	}

	/**
	 * Returns the name of the option with the stored data of a unit.
	 *
	 * @param string $number The registration number of the unit.
	 */
	private static function get_store_key( string $number ): string {
		return self::CACHE . '_' . md5( $number );
	}

	/**
	 * Returns the stored data of a unit: the data shown on the site and the state of the loading that is in progress.
	 *
	 * @param string $number The registration number of the unit.
	 *
	 * @phpstan-return array{data: Data|null, state: State|null}
	 * @return array{data: array<string, mixed>|null, state: array<string, mixed>|null}
	 */
	private static function get_record( string $number ): array {
		$record = get_option( self::get_store_key( $number ), array() );
		$data   = is_array( $record ) && isset( $record['data']['units'], $record['data']['errors'], $record['data']['loaded'], $record['data']['truncated'] ) ? $record['data'] : null;
		$state  = is_array( $record ) && isset( $record['state']['next'], $record['state']['units'], $record['state']['errors'] ) && array_key_exists( 'nodes', $record['state'] ) ? $record['state'] : null;

		/**
		 * The record stored by save_record().
		 *
		 * @phpstan-var array{data: Data|null, state: State|null} $result
		 */
		$result = array(
			'data'  => $data,
			'state' => $state,
		);

		return $result;
	}

	/**
	 * Stores the data of a unit.
	 *
	 * @param string                                                                   $number The registration number of the unit.
	 * @param array{data: array<string, mixed>|null, state: array<string, mixed>|null} $record The record.
	 *
	 * @return void
	 */
	private static function save_record( string $number, array $record ) {
		update_option( self::get_store_key( $number ), $record, false );
		$numbers = get_option( self::CACHED_NUMBERS_OPTION, array() );
		$numbers = is_array( $numbers ) ? $numbers : array();
		if ( ! in_array( $number, $numbers, true ) ) {
			$numbers[] = $number;
			update_option( self::CACHED_NUMBERS_OPTION, $numbers, false );
		}
	}

	/**
	 * Plans the loading of the data of a unit in the background, by WP-Cron.
	 *
	 * @param string $number The registration number of the unit.
	 *
	 * @return void
	 */
	private static function schedule_refresh( string $number ) {
		if ( false === wp_next_scheduled( self::REFRESH_HOOK, array( $number ) ) ) {
			wp_schedule_single_event( time(), self::REFRESH_HOOK, array( $number ) );
		}
	}

	/**
	 * Checks whether the data shown on the site should be loaded again: once a day, or once an hour when they are empty or incomplete.
	 *
	 * @phpstan-param Data $data
	 * @param array<string, mixed> $data The data.
	 */
	private static function is_stale( array $data ): bool {
		$age = count( $data['units'] ) > 0 && true !== $data['truncated'] ? DAY_IN_SECONDS : intdiv( DAY_IN_SECONDS, 24 );

		return intval( $data['loaded'] ) < time() - $age;
	}

	/**
	 * Returns the recruitment data of a unit and the units below it.
	 *
	 * The stored data are returned right away and loaded again in the background when they are old, so that the visitors never wait for SkautIS. Only the first time, a part of the data is loaded in the request and the rest in the background.
	 *
	 * @param string $number The registration number of the unit, or an empty string for the unit of the site.
	 *
	 * @phpstan-return Data
	 * @return array<string, mixed>
	 */
	public function get_data( string $number = '' ): array {
		$number = '' !== $number ? $number : self::get_unit_number();
		if ( isset( $this->data[ $number ] ) ) {
			return $this->data[ $number ];
		}

		$record = self::get_record( $number );
		if ( ! is_null( $record['data'] ) ) {
			if ( ! is_null( $record['state'] ) || self::is_stale( $record['data'] ) ) {
				self::schedule_refresh( $number );
			}
			$this->data[ $number ] = $record['data'];
			return $record['data'];
		}

		$state           = $this->process( $number, $record['state'] ?? self::new_state(), self::FIRST_TIME_LIMIT );
		$record['data']  = self::to_data( $state );
		$record['state'] = true === $state['complete'] ? null : $state;
		if ( ! is_null( $record['state'] ) ) {
			self::schedule_refresh( $number );
		}
		self::save_record( $number, $record );
		$this->data[ $number ] = $record['data'];

		return $record['data'];
	}

	/**
	 * Loads the data of a unit in the background, by WP-Cron. The data on the site are replaced when all units are loaded; until then, the loading continues in the next runs.
	 *
	 * @param string $number The registration number of the unit.
	 *
	 * @return void
	 */
	public function refresh( $number ) {
		$number     = strval( $number );
		$record     = self::get_record( $number );
		$state      = $this->process( $number, $record['state'] ?? self::new_state(), self::TIME_LIMIT );
		$shown      = $record['data'];
		$incomplete = is_array( $shown ) ? true === $shown['truncated'] : true;
		if ( true === $state['complete'] || $incomplete ) {
			// Complete data, or more of the data that were not complete yet.
			$record['data'] = self::to_data( $state );
		}
		$record['state'] = true === $state['complete'] ? null : $state;
		self::save_record( $number, $record );
		if ( ! is_null( $record['state'] ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::REFRESH_HOOK, array( $number ) );
		}
	}

	/**
	 * Returns the units with recruitment information.
	 *
	 * @param string $number The registration number of the unit, or an empty string for the unit of the site.
	 *
	 * @phpstan-return array<Unit>
	 * @return array<array<string, mixed>>
	 */
	public function get_units( string $number = '' ): array {
		return $this->get_data( $number )['units'];
	}

	/**
	 * Returns the state of a new loading.
	 *
	 * @phpstan-return State
	 * @return array<string, mixed>
	 */
	private static function new_state(): array {
		return array(
			'nodes'          => null,
			'next'           => 0,
			'units'          => array(),
			'errors'         => array(),
			'tree_truncated' => false,
			'complete'       => false,
		);
	}

	/**
	 * Returns the data shown on the site from the state of a loading.
	 *
	 * @phpstan-param State $state
	 * @param array<string, mixed> $state The state.
	 *
	 * @phpstan-return Data
	 * @return array<string, mixed>
	 */
	private static function to_data( array $state ): array {
		return array(
			'units'     => $state['units'],
			'errors'    => array_values( array_unique( $state['errors'] ) ),
			'loaded'    => time(),
			'truncated' => true !== $state['complete'] || true === $state['tree_truncated'],
		);
	}

	/**
	 * Calls a SkautIS method of the OrganizationUnit web service and returns the response as a list of objects.
	 *
	 * The blocks are on public pages, so the call never breaks them (see Skautis_Gateway::call()); a method that the App ID may not use is also shown in the notice in the administration.
	 *
	 * @param string               $method The name of the method.
	 * @param array<string, mixed> $args The arguments of the method.
	 *
	 * @return array<\stdClass>|null The objects or null when the call failed.
	 */
	private function call( string $method, array $args ) {
		$response = $this->skautis_gateway->call_list( 'OrganizationUnit', $method, $args );
		if ( $this->skautis_gateway->last_call_failed() ) {
			$this->errors[] = $method;
			return null;
		}
		Helpers::log_skautis_response( $method, $response );

		return $response;
	}


	/**
	 * Loads a unit, all units below it and their recruitment information from SkautIS, as many as it can in the time limit.
	 *
	 * Each card is in the group of its parent unit, the unit itself in its own group, so that e.g. the troops of a district are grouped by their groups (střediska).
	 *
	 * @param string               $number The registration number of the unit.
	 * @param array<string, mixed> $state The state of the loading, see new_state().
	 * @param int                  $limit The time limit in seconds.
	 *
	 * @phpstan-param State $state
	 * @phpstan-return State
	 * @return array<string, mixed> The new state of the loading.
	 */
	private function process( string $number, array $state, int $limit ): array {
		$this->errors = array();
		if ( '' === $number || ! $this->skautis_gateway->is_initialized() ) {
			$state['complete'] = true;
			return $state;
		}

		$start = microtime( true );
		if ( is_null( $state['nodes'] ) ) {
			$tree           = new Unit_Tree( $this->skautis_gateway );
			$state['nodes'] = array();
			foreach ( $tree->load( $number ) as $node ) {
				$state['nodes'][] = array(
					'id'          => Unit_Tree::get_id( $node['unit'] ),
					'name'        => Unit_Tree::get_string( $node['unit'], 'DisplayName' ),
					'number'      => Unit_Tree::get_string( $node['unit'], 'RegistrationNumber' ),
					'parent_id'   => $node['parent_id'],
					'parent_name' => $node['parent_name'],
					'depth'       => $node['depth'],
				);
			}
			$state['tree_truncated'] = $tree->is_truncated();
			if ( count( $state['nodes'] ) === 0 ) {
				$this->errors[] = 'UnitAll';
			}
		}

		$count = count( $state['nodes'] );
		while ( $state['next'] < $count && microtime( true ) - $start < $limit ) {
			$node = $state['nodes'][ $state['next'] ];
			++$state['next'];
			$loaded = $this->load_unit(
				(object) array(
					'ID'                 => $node['id'],
					'DisplayName'        => $node['name'],
					'RegistrationNumber' => $node['number'],
				)
			);
			if ( count( $loaded['categories'] ) === 0 && count( $loaded['meetings'] ) === 0 && '' === $loaded['note'] ) {
				continue;
			}
			$root                 = 0 === $node['depth'];
			$loaded['group_id']   = $root ? $loaded['id'] : $node['parent_id'];
			$loaded['group_name'] = $root ? $loaded['name'] : $node['parent_name'];
			$state['units'][]     = $loaded;
		}
		$state['errors']   = array_values( array_unique( array_merge( $state['errors'], $this->errors ) ) );
		$state['complete'] = $state['next'] >= $count;

		return $state;
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
			array_merge(
				array(
					'ID_Unit'           => $unit_id,
					'IncludeChildUnits' => false,
					'Publish'           => true,
				),
				self::WHOLE_MAP
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
			'group_id'            => 0,
			'group_name'          => '',
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
