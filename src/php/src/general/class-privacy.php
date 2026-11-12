<?php
/**
 * Contains the Privacy class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\General;

/**
 * Connects the plugin to the privacy tools of WordPress (GDPR).
 *
 * It suggests a text for the privacy policy of the site and lets the administrators export and erase the personal data that the plugin stores about a user: the connection to SkautIS and the profile photo from SkautIS.
 *
 * @phan-constructor-used-for-side-effects
 */
final class Privacy {

	/**
	 * The user meta with the SkautIS user IDs, in the form `meta key => environment`.
	 */
	const USER_ID_META = array(
		'skautisUserId_prod' => 'SkautIS',
		'skautisUserId_test' => 'SkautIS (test)',
	);

	/**
	 * Constructs the service and registers the hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( self::class, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	/**
	 * Adds the suggested text to the privacy policy guide (Settings → Privacy).
	 *
	 * @return void
	 */
	public static function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$paragraphs = array(
			'<strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'skautis-integration' ) . '</strong> ' . esc_html__( 'This site lets members of Junák – český skaut log in and register with their SkautIS account. SkautIS is the information system of Junák – český skaut, which is also its operator.', 'skautis-integration' ),
			esc_html__( 'When you log in or register through SkautIS, this site receives from SkautIS your user ID and, for a registration, your name, nickname and e-mail address, which are stored with your account on this site. To decide whether you may register or see some content, this site checks your roles, memberships, functions, qualifications or events in SkautIS; these data are only used for the check and are not stored.', 'skautis-integration' ),
			esc_html__( 'If you choose so in your profile, this site downloads your photo from SkautIS and uses it as your profile picture. You can remove it in your profile at any time.', 'skautis-integration' ),
			esc_html__( 'During the login through SkautIS, this site sets a technical cookie (skautis-integration-skautis-session) that keeps your SkautIS login for 40 minutes.', 'skautis-integration' ),
			esc_html__( 'The maps on this site load map tiles from Mapy.com (Seznam.cz, a.s.) or from OpenStreetMap; your browser sends your IP address to these services when it shows a map. The addresses of the units are looked up with Mapy.com or Nominatim of OpenStreetMap; no data about you are sent in this case.', 'skautis-integration' ),
			esc_html__( 'The administrators of the site can export or erase the data that this plugin stores about you with the privacy tools of WordPress. The processing of personal data in SkautIS is described by Junák – český skaut at https://www.skaut.cz/osobniudaje.', 'skautis-integration' ),
		);

		wp_add_privacy_policy_content( 'SkautIS integration', '<p>' . implode( '</p><p>', $paragraphs ) . '</p>' );
	}

	/**
	 * Registers the exporter of the personal data.
	 *
	 * @param array<string, array{exporter_friendly_name: string, callback: callable}> $exporters The exporters.
	 *
	 * @return array<string, array{exporter_friendly_name: string, callback: callable}>
	 */
	public static function register_exporter( $exporters ) {
		$exporters[ SKAUTIS_INTEGRATION_NAME ] = array(
			'exporter_friendly_name' => __( 'SkautIS integration', 'skautis-integration' ),
			'callback'               => array( self::class, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Registers the eraser of the personal data.
	 *
	 * @param array<string, array{eraser_friendly_name: string, callback: callable}> $erasers The erasers.
	 *
	 * @return array<string, array{eraser_friendly_name: string, callback: callable}>
	 */
	public static function register_eraser( $erasers ) {
		$erasers[ SKAUTIS_INTEGRATION_NAME ] = array(
			'eraser_friendly_name' => __( 'SkautIS integration', 'skautis-integration' ),
			'callback'             => array( self::class, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Exports the personal data that the plugin stores about a user.
	 *
	 * @param string $email The e-mail address of the user.
	 *
	 * @return array{data: array<array{group_id: string, group_label: string, item_id: string, data: array<array{name: string, value: string}>}>, done: bool}
	 */
	public static function export( $email ): array {
		$user = get_user_by( 'email', $email );
		if ( false === $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$data = array();
		foreach ( self::USER_ID_META as $meta => $environment ) {
			$skautis_user_id = strval( get_user_meta( $user->ID, $meta, true ) );
			if ( '' !== $skautis_user_id ) {
				$data[] = array(
					/* translators: %s: SkautIS or SkautIS (test) */
					'name'  => sprintf( __( 'User ID in %s', 'skautis-integration' ), $environment ),
					'value' => $skautis_user_id,
				);
			}
		}
		$photo_id = Profile_Photo::get_photo_id( $user->ID );
		if ( 0 !== $photo_id ) {
			$url    = wp_get_attachment_url( $photo_id );
			$data[] = array(
				'name'  => __( 'Profile photo from SkautIS', 'skautis-integration' ),
				'value' => false !== $url ? $url : '',
			);
			$data[] = array(
				'name'  => __( 'Download the photo again at every login through SkautIS', 'skautis-integration' ),
				'value' => '1' === get_user_meta( $user->ID, Profile_Photo::AUTO_META, true ) ? __( 'Yes', 'skautis-integration' ) : __( 'No', 'skautis-integration' ),
			);
		}

		return array(
			'data' => count( $data ) > 0 ? array(
				array(
					'group_id'    => SKAUTIS_INTEGRATION_NAME,
					'group_label' => __( 'SkautIS', 'skautis-integration' ),
					'item_id'     => SKAUTIS_INTEGRATION_NAME . '-' . $user->ID,
					'data'        => $data,
				),
			) : array(),
			'done' => true,
		);
	}

	/**
	 * Erases the personal data that the plugin stores about a user: the connection to SkautIS and the profile photo.
	 *
	 * The user account itself is kept, the user can connect it to SkautIS again.
	 *
	 * @param string $email The e-mail address of the user.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: array<string>, done: bool}
	 */
	public static function erase( $email ): array {
		$user    = get_user_by( 'email', $email );
		$removed = false;
		if ( false !== $user ) {
			foreach ( array_keys( self::USER_ID_META ) as $meta ) {
				if ( '' !== strval( get_user_meta( $user->ID, $meta, true ) ) ) {
					delete_user_meta( $user->ID, $meta );
					$removed = true;
				}
			}
			if ( 0 !== Profile_Photo::get_photo_id( $user->ID ) ) {
				$removed = true;
			}
			Profile_Photo::remove_photo( $user->ID );
			delete_user_meta( $user->ID, Profile_Photo::AUTO_META );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => $removed ? array( __( 'The account was disconnected from SkautIS and the profile photo from SkautIS was removed.', 'skautis-integration' ) ) : array(),
			'done'           => true,
		);
	}
}
