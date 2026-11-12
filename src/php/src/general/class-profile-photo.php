<?php
/**
 * Contains the Profile_Photo class.
 *
 * @package skautis-integration
 */

declare( strict_types=1 );

namespace Skautis_Integration\General;

use PHPMD\Attribute\SuppressWarnings;
use PHPMD\Rule\Design\ExcessiveClassComplexity;
use PHPMD\Rule\Design\ExitExpression;
use Skautis_Integration\Auth\Skautis_Gateway;
use Skautis_Integration\Auth\Skautis_Login;
use Skautis_Integration\Utils\Helpers;
use Skautis_Integration\Utils\Request_Parameter_Helpers;

/**
 * Lets every user use their photo from SkautIS as their profile picture in WordPress.
 *
 * Nothing is downloaded on its own: each user decides in their own profile whether to download the photo, and may let it be updated at every login through SkautIS. The photo is stored as an attachment of the media library and replaces the avatar (Gravatar) of the user. The user or an administrator can remove it at any time; it is also removed with the user.
 *
 * @phan-constructor-used-for-side-effects
 */
#[SuppressWarnings( ExcessiveClassComplexity::class )]
final class Profile_Photo {

	/**
	 * The user meta with the ID of the attachment with the photo.
	 */
	const PHOTO_META = SKAUTIS_INTEGRATION_NAME . '_photo';

	/**
	 * The user meta with the hash of the downloaded photo, so the same photo is not stored again.
	 */
	const HASH_META = SKAUTIS_INTEGRATION_NAME . '_photo_hash';

	/**
	 * The user meta that is '1' when the user wants the photo to be updated at every login through SkautIS.
	 */
	const AUTO_META = SKAUTIS_INTEGRATION_NAME . '_photo_auto';

	/**
	 * The admin-post action that downloads the photo.
	 */
	const IMPORT_ACTION = SKAUTIS_INTEGRATION_NAME . '_photo_import';

	/**
	 * The admin-post action that removes the photo.
	 */
	const REMOVE_ACTION = SKAUTIS_INTEGRATION_NAME . '_photo_remove';

	/**
	 * The query parameter with the result of an action, for the notice in the profile.
	 */
	const RESULT_PARAMETER = SKAUTIS_INTEGRATION_NAME . '_photo';

	/**
	 * The width and height of the stored photo in pixels.
	 */
	const SIZE = 512;

	/**
	 * A link to the Skautis_Gateway service instance.
	 *
	 * @var Skautis_Gateway
	 */
	private $skautis_gateway;

	/**
	 * A link to the Skautis_Login service instance.
	 *
	 * @var Skautis_Login
	 */
	private $skautis_login;

	/**
	 * Constructs the service and saves all dependencies.
	 *
	 * @param Skautis_Gateway $skautis_gateway An injected Skautis_Gateway service instance.
	 * @param Skautis_Login   $skautis_login An injected Skautis_Login service instance.
	 */
	public function __construct( Skautis_Gateway $skautis_gateway, Skautis_Login $skautis_login ) {
		$this->skautis_gateway = $skautis_gateway;
		$this->skautis_login   = $skautis_login;

		add_filter( 'pre_get_avatar_data', array( self::class, 'filter_avatar_data' ), 10, 2 );
		add_filter( 'user_profile_picture_description', array( self::class, 'filter_picture_description' ), 10, 2 );
		add_action( 'show_user_profile', array( $this, 'print_profile_section' ), 11 );
		add_action( 'edit_user_profile', array( $this, 'print_profile_section' ), 11 );
		add_action( 'personal_options_update', array( self::class, 'save_auto_update' ) );
		add_action( 'admin_notices', array( self::class, 'print_notice' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
		add_action( 'admin_post_' . self::REMOVE_ACTION, array( self::class, 'handle_remove' ) );
		add_action( 'wp_login', array( $this, 'update_at_login' ), 20, 2 );
		add_action( 'delete_user', array( self::class, 'remove_photo' ) );
	}

	/**
	 * Returns the ID of the attachment with the photo of a user, or 0 when the user has none.
	 *
	 * @param int $user_id The ID of the user.
	 */
	public static function get_photo_id( int $user_id ): int {
		$attachment_id = intval( get_user_meta( $user_id, self::PHOTO_META, true ) );

		return $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ? $attachment_id : 0;
	}

	/**
	 * Finds the ID of the user from the argument of get_avatar().
	 *
	 * @param mixed $id_or_email A user ID, an e-mail address, a WP_User, a WP_Post or a WP_Comment.
	 */
	private static function get_avatar_user_id( $id_or_email ): int {
		if ( is_numeric( $id_or_email ) ) {
			return intval( $id_or_email );
		}
		if ( $id_or_email instanceof \WP_User ) {
			return $id_or_email->ID;
		}
		if ( $id_or_email instanceof \WP_Post ) {
			return intval( $id_or_email->post_author );
		}
		if ( $id_or_email instanceof \WP_Comment ) {
			return intval( $id_or_email->user_id );
		}
		if ( is_string( $id_or_email ) && false !== is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );

			return false !== $user ? $user->ID : 0;
		}

		return 0;
	}

	/**
	 * Uses the photo from SkautIS as the avatar of the user.
	 *
	 * @param array<string, mixed> $args The arguments of the avatar.
	 * @param mixed                $id_or_email The user, see get_avatar().
	 *
	 * @return array<string, mixed> The arguments of the avatar.
	 */
	public static function filter_avatar_data( $args, $id_or_email ) {
		if ( isset( $args['force_default'] ) && true === $args['force_default'] ) {
			return $args;
		}
		$user_id = self::get_avatar_user_id( $id_or_email );
		if ( $user_id <= 0 ) {
			return $args;
		}
		$attachment_id = self::get_photo_id( $user_id );
		if ( 0 === $attachment_id ) {
			return $args;
		}
		$size = isset( $args['size'] ) && is_numeric( $args['size'] ) ? intval( $args['size'] ) : 96;
		$url  = wp_get_attachment_image_url( $attachment_id, array( $size, $size ) );
		if ( false !== $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;
		}

		return $args;
	}

	/**
	 * Changes the description of the profile picture in the profile when the photo from SkautIS is used.
	 *
	 * @param string   $description The description.
	 * @param \WP_User $user The user.
	 */
	public static function filter_picture_description( $description, $user ): string {
		if ( 0 !== self::get_photo_id( $user->ID ) ) {
			return esc_html__( 'Your photo from SkautIS is used as the profile picture. You can update or remove it in the "Profile photo from SkautIS" section below.', 'skautis-integration' );
		}

		return $description;
	}

	/**
	 * Returns the SkautIS user ID that is connected to a WordPress user, or 0.
	 *
	 * @param int $user_id The ID of the WordPress user.
	 */
	private function get_connected_skautis_user_id( int $user_id ): int {
		return intval( get_user_meta( $user_id, 'skautisUserId_' . $this->skautis_gateway->get_env(), true ) );
	}

	/**
	 * Returns the URL of an admin-post action with a nonce.
	 *
	 * @param string $action The action.
	 * @param int    $user_id The ID of the user.
	 */
	private static function get_action_url( string $action, int $user_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => $action,
					'user_id' => $user_id,
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $user_id
		);
	}

	/**
	 * Prints the section with the photo from SkautIS in the profile of a user.
	 *
	 * The photo can be downloaded only by the user themselves, an administrator can only remove it.
	 *
	 * @param \WP_User $user The user whose profile is shown.
	 *
	 * @return void
	 */
	public function print_profile_section( \WP_User $user ) {
		$own_profile   = get_current_user_id() === $user->ID;
		$attachment_id = self::get_photo_id( $user->ID );
		if ( ! $own_profile && 0 === $attachment_id ) {
			return;
		}
		?>
		<h3 id="skautis-integration-photo"><?php esc_html_e( 'Profile photo from SkautIS', 'skautis-integration' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Photo', 'skautis-integration' ); ?></th>
				<td>
					<?php
					if ( 0 !== $attachment_id ) {
						echo wp_get_attachment_image( $attachment_id, array( 96, 96 ), false, array( 'style' => 'display: block; margin-bottom: 8px; border-radius: 50%;' ) );
					}
					if ( $own_profile ) {
						if ( $this->get_connected_skautis_user_id( $user->ID ) > 0 ) {
							echo '<a class="button" href="' . esc_url( self::get_action_url( self::IMPORT_ACTION, $user->ID ) ) . '">' . esc_html( 0 !== $attachment_id ? __( 'Update the photo from SkautIS', 'skautis-integration' ) : __( 'Use my photo from SkautIS', 'skautis-integration' ) ) . '</a> ';
						}
					}
					if ( 0 !== $attachment_id ) {
						echo '<a class="button" href="' . esc_url( self::get_action_url( self::REMOVE_ACTION, $user->ID ) ) . '">' . esc_html__( 'Remove the photo', 'skautis-integration' ) . '</a>';
					}
					if ( $own_profile ) {
						echo '<p class="description">';
						if ( $this->get_connected_skautis_user_id( $user->ID ) > 0 ) {
							esc_html_e( 'Your photo from SkautIS can be used as your profile picture on this site, instead of Gravatar. It is downloaded only when you click the button, and you can remove it at any time. If you are not logged in to SkautIS, you will be asked to log in first.', 'skautis-integration' );
						} else {
							esc_html_e( 'Connect your account to SkautIS first, then you can use your photo from SkautIS as your profile picture.', 'skautis-integration' );
						}
						echo '</p>';
					}
					?>
				</td>
			</tr>
			<?php if ( $own_profile && $this->get_connected_skautis_user_id( $user->ID ) > 0 ) { ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Updating the photo', 'skautis-integration' ); ?></th>
				<td>
					<label for="<?php echo esc_attr( self::AUTO_META ); ?>">
						<input type="checkbox" name="<?php echo esc_attr( self::AUTO_META ); ?>" id="<?php echo esc_attr( self::AUTO_META ); ?>" value="1" <?php checked( '1', get_user_meta( $user->ID, self::AUTO_META, true ) ); ?> />
						<?php esc_html_e( 'Download my photo from SkautIS again at every login through SkautIS', 'skautis-integration' ); ?>
					</label>
				</td>
			</tr>
			<?php } ?>
		</table>
		<?php
	}

	/**
	 * Saves the choice of the user to update the photo at every login.
	 *
	 * WordPress checks the nonce of the profile form before this action.
	 *
	 * @param int $user_id The ID of the user.
	 *
	 * @return void
	 */
	public static function save_auto_update( $user_id ) {
		$user_id = intval( $user_id );
		if ( get_current_user_id() !== $user_id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by WordPress before the personal_options_update action.
		if ( isset( $_POST[ self::AUTO_META ] ) ) {
			update_user_meta( $user_id, self::AUTO_META, '1' );
		} else {
			delete_user_meta( $user_id, self::AUTO_META );
		}
	}

	/**
	 * Checks the nonce of an admin-post action and returns the ID of the user it is for.
	 *
	 * @param string $action The action.
	 */
	private static function verify_action( string $action ): int {
		$user_id = Request_Parameter_Helpers::get_int_variable( 'user_id', 0 );
		if ( $user_id <= 0 || false === wp_verify_nonce( Request_Parameter_Helpers::get_string_variable( '_wpnonce' ), $action . '_' . $user_id ) ) {
			wp_die( esc_html__( 'The link has expired. Go back to the profile and try it again.', 'skautis-integration' ), '', array( 'response' => 403 ) );
		}

		return $user_id;
	}

	/**
	 * Redirects back to the profile with the result of an action.
	 *
	 * @param int    $user_id The ID of the user.
	 * @param string $result The result.
	 *
	 * @return never
	 */
	#[SuppressWarnings( ExitExpression::class )]
	private static function redirect_to_profile( int $user_id, string $result ) {
		$url = get_current_user_id() === $user_id ? admin_url( 'profile.php' ) : add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) );
		wp_safe_redirect( add_query_arg( self::RESULT_PARAMETER, $result, $url ) . '#skautis-integration-photo', 302 );
		exit();
	}

	/**
	 * Downloads the photo of the current user from SkautIS, after logging in to SkautIS when needed.
	 *
	 * @return void
	 */
	#[SuppressWarnings( ExitExpression::class )]
	public function handle_import() {
		$user_id = self::verify_action( self::IMPORT_ACTION );
		if ( get_current_user_id() !== $user_id ) {
			wp_die( esc_html__( 'Only the user themselves can download their photo from SkautIS.', 'skautis-integration' ), '', array( 'response' => 403 ) );
		}
		if ( 0 === $this->get_connected_skautis_user_id( $user_id ) ) {
			self::redirect_to_profile( $user_id, 'not_connected' );
		}
		if ( ! $this->skautis_login->is_user_logged_in_skautis() ) {
			// SkautIS returns to this link after the login.
			wp_safe_redirect( esc_url_raw( $this->skautis_gateway->get_skautis_instance()->getLoginUrl( Helpers::get_current_url() ) ), 302 );
			exit();
		}

		self::redirect_to_profile( $user_id, $this->import( $user_id ) );
	}

	/**
	 * Removes the photo of a user, the user themselves or an administrator who can edit the user.
	 *
	 * @return void
	 */
	public static function handle_remove() {
		$user_id = self::verify_action( self::REMOVE_ACTION );
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to remove the photo of this user.', 'skautis-integration' ), '', array( 'response' => 403 ) );
		}
		self::remove_photo( $user_id );
		delete_user_meta( $user_id, self::AUTO_META );

		self::redirect_to_profile( $user_id, 'removed' );
	}

	/**
	 * Updates the photo at a login through SkautIS when the user has chosen it.
	 *
	 * @param string   $user_login The login name of the user.
	 * @param \WP_User $user The user.
	 *
	 * @return void
	 */
	public function update_at_login( $user_login, $user ) {
		unset( $user_login );
		if ( '1' !== get_user_meta( $user->ID, self::AUTO_META, true ) || ! $this->skautis_login->is_user_logged_in_skautis() ) {
			return;
		}
		$this->import( $user->ID );
	}

	/**
	 * Prints the result of an action in the profile.
	 *
	 * @return void
	 */
	public static function print_notice() {
		$screen = get_current_screen();
		if ( is_null( $screen ) || ! in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) {
			return;
		}
		$messages = array(
			'imported'      => array( 'success', __( 'Your photo from SkautIS is now your profile picture.', 'skautis-integration' ) ),
			'unchanged'     => array( 'success', __( 'Your photo in SkautIS has not changed, the profile picture is up to date.', 'skautis-integration' ) ),
			'removed'       => array( 'success', __( 'The photo from SkautIS was removed.', 'skautis-integration' ) ),
			'no_photo'      => array( 'warning', __( 'You do not have a photo in SkautIS. You can add it in SkautIS on the page of your person.', 'skautis-integration' ) ),
			'not_connected' => array( 'warning', __( 'Your account is not connected to SkautIS.', 'skautis-integration' ) ),
			'other_user'    => array( 'error', __( 'You are logged in to SkautIS as another user than the one connected to this account. Log out of SkautIS and try it again.', 'skautis-integration' ) ),
			'error'         => array( 'error', __( 'The photo could not be downloaded from SkautIS. The App ID of this site may not be allowed to use it, or the photo could not be saved.', 'skautis-integration' ) ),
		);
		$result   = Request_Parameter_Helpers::get_string_variable( self::RESULT_PARAMETER );
		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $messages[ $result ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $result ][1] ) . '</p></div>';
	}

	/**
	 * Returns the content of the photo from the response of PersonPhoto.
	 *
	 * The SOAP client decodes the photo from base64, an already decoded photo is accepted too.
	 *
	 * @param mixed $response The response of PersonPhoto.
	 *
	 * @return string The binary content of the image, or an empty string.
	 */
	private static function get_photo_content( $response ): string {
		if ( ! ( $response instanceof \stdClass ) ) {
			return '';
		}
		foreach ( array( 'PhotoContent', 'PhotoBigContent', 'PhotoNormalContent', 'PhotoMediumContent', 'PhotoSmallContent' ) as $field ) {
			if ( ! isset( $response->$field ) || ! is_string( $response->$field ) || '' === $response->$field ) {
				continue;
			}
			$content = $response->$field;
			if ( false === @getimagesizefromstring( $content ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Not an image is a valid result here.
				$decoded = base64_decode( $content, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- The photo may come in base64.
				$content = false !== $decoded ? $decoded : '';
			}
			if ( '' !== $content && false !== @getimagesizefromstring( $content ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- See above.
				return $content;
			}
		}

		return '';
	}

	/**
	 * Downloads the photo of the logged-in SkautIS user and stores it as the profile picture of a WordPress user.
	 *
	 * @param int $user_id The ID of the WordPress user, which must be connected to the logged-in SkautIS user.
	 *
	 * @return string The result: imported, unchanged, no_photo, other_user or error.
	 */
	private function import( int $user_id ): string {
		$user_detail = $this->skautis_gateway->get_user_detail();
		if ( is_null( $user_detail ) || ! isset( $user_detail->ID_Person ) ) {
			return 'error';
		}
		if ( intval( $user_detail->ID ) !== $this->get_connected_skautis_user_id( $user_id ) ) {
			return 'other_user';
		}

		$response = $this->skautis_gateway->call(
			'OrganizationUnit',
			'PersonPhoto',
			array(
				'ID'   => intval( $user_detail->ID_Person ),
				'Size' => 'big',
			)
		);
		if ( $this->skautis_gateway->last_call_failed() ) {
			return 'error';
		}
		$content = self::get_photo_content( $response );
		Helpers::log_skautis_response(
			'OrganizationUnit->PersonPhoto',
			array(
				'fields' => $response instanceof \stdClass ? array_keys( get_object_vars( $response ) ) : gettype( $response ),
				'bytes'  => strlen( $content ),
			)
		);
		if ( '' === $content ) {
			return 'no_photo';
		}

		$hash = md5( $content );
		if ( 0 !== self::get_photo_id( $user_id ) && get_user_meta( $user_id, self::HASH_META, true ) === $hash ) {
			return 'unchanged';
		}

		$attachment_id = self::store( $user_id, $content );
		if ( 0 === $attachment_id ) {
			return 'error';
		}
		self::remove_photo( $user_id );
		update_user_meta( $user_id, self::PHOTO_META, $attachment_id );
		update_user_meta( $user_id, self::HASH_META, $hash );

		return 'imported';
	}

	/**
	 * Crops the photo to a square, which suits the avatars, and stores it in the media library.
	 *
	 * @param int    $user_id The ID of the user.
	 * @param string $content The binary content of the image.
	 *
	 * @return int The ID of the attachment, or 0 on an error.
	 */
	private static function store( int $user_id, string $content ): int {
		// The functions of the media library are not loaded e.g. at the login.
		require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound (WordPress is not analysed.)
		require_once ABSPATH . 'wp-admin/includes/media.php'; // @phpstan-ignore requireOnce.fileNotFound (WordPress is not analysed.)
		require_once ABSPATH . 'wp-admin/includes/image.php'; // @phpstan-ignore requireOnce.fileNotFound (WordPress is not analysed.)

		// The image editor recognizes the image by the extension of the file.
		$info       = getimagesizefromstring( $content );
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		if ( false === $info || ! isset( $extensions[ $info['mime'] ] ) ) {
			return 0;
		}
		$temporary = wp_tempnam( 'skautis-photo' );
		if ( '' === $temporary ) {
			return 0;
		}
		wp_delete_file( $temporary );
		$source = $temporary . '.' . $extensions[ $info['mime'] ];
		if ( false === file_put_contents( $source, $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A temporary file.
			return 0;
		}

		$editor = wp_get_image_editor( $source );
		if ( $editor instanceof \WP_Error ) {
			wp_delete_file( $source );
			return 0;
		}
		$size   = $editor->get_size();
		$side   = min( $size['width'], $size['height'] );
		$top    = intval( ( $size['height'] - $side ) / 3 ); // The face is usually in the upper part of a portrait.
		$left   = intval( ( $size['width'] - $side ) / 2 );
		$target = min( $side, self::SIZE );
		$editor->crop( $left, $top, $side, $side, $target, $target );
		$saved = $editor->save( $temporary . '-square.jpg', 'image/jpeg' );
		wp_delete_file( $source );
		if ( $saved instanceof \WP_Error ) {
			return 0;
		}

		$file          = array(
			'name'     => 'skautis-photo-' . $user_id . '.jpg',
			'tmp_name' => $saved['path'],
		);
		$attachment_id = media_handle_sideload(
			$file,
			0,
			/* translators: %s: The display name of the user */
			sprintf( __( 'Profile photo from SkautIS: %s', 'skautis-integration' ), get_the_author_meta( 'display_name', $user_id ) ),
			array( 'post_author' => $user_id )
		);
		if ( $attachment_id instanceof \WP_Error ) {
			wp_delete_file( $saved['path'] );
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * Removes the stored photo of a user, also when the user is deleted.
	 *
	 * @param int $user_id The ID of the user.
	 *
	 * @return void
	 */
	public static function remove_photo( $user_id ) {
		$user_id       = intval( $user_id );
		$attachment_id = self::get_photo_id( $user_id );
		if ( 0 !== $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
		delete_user_meta( $user_id, self::PHOTO_META );
		delete_user_meta( $user_id, self::HASH_META );
	}
}
