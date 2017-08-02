<?php
/**
 * BoldGrid Library Plugin Installer Activate
 *
 * @package Boldgrid\Library
 * @subpackage \Library\Plugin
 *
 * @version 1.0.0
 * @author BoldGrid <wpb@boldgrid.com>
 */

namespace Boldgrid\Library\Library\Plugin\Installer;

use Boldgrid\Library\Library;
use Boldgrid\Library\Util;

/**
 * BoldGrid Library Plugin Installer Class.
 *
 * This class is responsible for installing BoldGrid plugins in the WordPress
 * admin's "Plugins > Add New" section.
 *
 * @since 1.0.0
 */
class Activate {

	/**
	 * @var array $configs library configuration options.
	 */
	protected $configs;

	/**
	 * Initiallize class and set class properties.
	 *
	 * @param array $configs array of library configuration options.
	 */
	public function __construct( array $configs ) {
		$this->configs = $configs;
	}

	/**
	 * Adds filters.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		Library\Filter::add( $this );
	}

	/**
	 * Called via Ajax for activating a WordPress plugin.
	 *
	 * This is used for allowing the installed plugins listed in our tab to be
	 * activated.
	 *
	 * @since 1.0.0
	 *
	 * @hook: wp_ajax_activation
	 *
	 * @return $json
	 */
	public function activation() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'Sorry, you are not allowed to activate plugins on this site.', 'boldgrid-library' ) );
		}

		$nonce = $_POST['nonce'];
		$plugin = $_POST['plugin'];

		// Check our nonce, if they don't match then bounce!
		if ( ! wp_verify_nonce( $nonce, 'bglibPluginInstallerNonce' ) ) {
			die( __( 'Error - unable to verify nonce, please try again.', 'boldgrid-library' ) );
		}

		// Include required libs for activation.
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

		// Get Plugin Info.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug' => $plugin,
				'fields' => array(
					'short_description' => false,
					'sections' => false,
					'requires' => false,
					'rating' => false,
					'ratings' => false,
					'downloaded' => false,
					'last_updated' => false,
					'added' => false,
					'tags' => false,
					'compatibility' => false,
					'homepage' => false,
					'donate_link' => false,
				),
			)
		);

		if ( $api->name ) {
			$file = ! empty( $file = $this->configs['plugins'][ $plugin ]['file'] ) ? $this->configs['plugins'][ $plugin ]['file'] : Util\Plugin::getPluginFile( $api->slug );
			$status = 'success';
			if ( $file ) {
				$activate = activate_plugin( $file );
				if ( is_wp_error( $activate ) ) {
					// Process Error
					$msg = sprintf( __( 'There was an error activating %s.', 'boldgrid-library' ), $api->name );
					wp_send_json_error( array( 'message' => $msg ) );
				}
				$msg = sprintf( __( '%s successfully activated.', 'boldgrid-library' ),  $api->name );
				wp_send_json_success( array( 'message' => $msg ) );
			}
		} else {
			$status = 'failed';
			$msg = sprintf( __( 'There was an error activating %s.', 'boldgrid-library' ), $api->name );
			wp_send_json_error( array( 'message' => $msg ) );
		}
	}
}
