<?php
/**
 * Contains the Recruitment class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\Modules\Recruitment;

use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Modules\Recruitment\Admin\Appearances_Page;
use Skautis_Integration\Modules\Recruitment\Admin\Settings;
use Skautis_Integration\Modules\Recruitment\Frontend\Blocks;

/**
 * Shows the recruitment information and the map of the units from SkautIS on the site.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Recruitment {

	/**
	 * Constructs the service and sets up the blocks and the settings.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway ) {
		$recruitment_data = new Recruitment_Data( $skautis_gateway );
		add_action( Recruitment_Data::REFRESH_HOOK, array( $recruitment_data, 'refresh' ) );
		new Unit_List( $skautis_gateway );
		// The blocks are needed in all requests, the block editor renders them through the REST API.
		new Blocks( $recruitment_data );
		if ( is_admin() ) {
			new Settings( $recruitment_data );
			new Appearances_Page();
		}
	}
}
