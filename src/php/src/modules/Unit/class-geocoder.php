<?php
/**
 * Contains the Geocoder class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Unit;

use Skautis_Integration\Admin\Maps_Settings;

/**
 * Finds the GPS coordinates of an address, for the map of the seat of a unit.
 *
 * It uses the geocoding of Mapy.com when the API key of Mapy.com is set, otherwise Nominatim of OpenStreetMap. The result is stored with the data of the unit, so an address is looked up at most once a day.
 */
final class Geocoder {

	/**
	 * Returns the GPS coordinates of an address.
	 *
	 * @param string $address The address.
	 *
	 * @return array{lat: float, lng: float} The coordinates, zeros when the address was not found.
	 */
	public static function geocode( string $address ): array {
		$key = Maps_Settings::get_mapy_api_key();

		return '' !== $key ? self::geocode_mapy( $address, $key ) : self::geocode_nominatim( $address );
	}

	/**
	 * Returns the decoded JSON response of a URL, or null on an error.
	 *
	 * @param string $url The URL.
	 *
	 * @return mixed
	 */
	private static function get_json( string $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 5,
				// Nominatim requires an identification of the application.
				'user-agent' => 'skautis-integration WordPress plugin (' . home_url() . ')',
			)
		);
		// @phpstan-ignore argument.type (The WordPress stubs describe the response more strictly than wp_remote_get() returns it.)
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Finds an address with the geocoding of Mapy.com.
	 *
	 * @param string $address The address.
	 * @param string $key The API key of Mapy.com.
	 *
	 * @return array{lat: float, lng: float}
	 */
	private static function geocode_mapy( string $address, string $key ): array {
		$data     = self::get_json(
			add_query_arg(
				array(
					'query'  => rawurlencode( $address ),
					'lang'   => 'cs',
					'limit'  => 1,
					'apikey' => rawurlencode( $key ),
				),
				'https://api.mapy.com/v1/geocode'
			)
		);
		$position = is_array( $data ) && isset( $data['items'][0]['position'] ) && is_array( $data['items'][0]['position'] ) ? $data['items'][0]['position'] : array();

		return array(
			'lat' => isset( $position['lat'] ) && is_numeric( $position['lat'] ) ? floatval( $position['lat'] ) : 0.0,
			'lng' => isset( $position['lon'] ) && is_numeric( $position['lon'] ) ? floatval( $position['lon'] ) : 0.0,
		);
	}

	/**
	 * Finds an address with Nominatim of OpenStreetMap.
	 *
	 * @param string $address The address.
	 *
	 * @return array{lat: float, lng: float}
	 */
	private static function geocode_nominatim( string $address ): array {
		// The usage policy of Nominatim allows at most one request per second, e.g. for the child units of a district.
		static $last = 0.0;
		$wait        = 1.0 - ( microtime( true ) - $last );
		if ( $wait > 0 ) {
			usleep( intval( $wait * 1000000 ) );
		}
		$last  = microtime( true );
		$data  = self::get_json(
			add_query_arg(
				array(
					'q'            => rawurlencode( $address ),
					'format'       => 'jsonv2',
					'limit'        => 1,
					'countrycodes' => 'cz',
				),
				'https://nominatim.openstreetmap.org/search'
			)
		);
		$place = is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : array();

		return array(
			'lat' => isset( $place['lat'] ) && is_numeric( $place['lat'] ) ? floatval( $place['lat'] ) : 0.0,
			'lng' => isset( $place['lon'] ) && is_numeric( $place['lon'] ) ? floatval( $place['lon'] ) : 0.0,
		);
	}
}
