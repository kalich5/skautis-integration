<?php
/**
 * Contains the Unit class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Unit;

use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Modules\Unit\Admin\Settings;
use Skautis_Integration\Modules\Unit\Frontend\Unit_Block;

/**
 * Shows the public information about a unit from SkautIS on the site.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Unit {

	/**
	 * Constructs the service and sets up the block and the settings.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway ) {
		$unit_data = new Unit_Data( $skautis_gateway );
		// The block is needed in all requests, the block editor renders it through the REST API.
		new Unit_Block( $unit_data );
		if ( is_admin() ) {
			new Settings( $unit_data );
		}
	}
}
