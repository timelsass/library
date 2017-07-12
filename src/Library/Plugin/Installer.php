<?php
/**
 * BoldGrid Library Plugin Installer
 *
 * @package Boldgrid\Library
 * @subpackage \Library\Plugin
 *
 * @version 1.0.0
 * @author BoldGrid <wpb@boldgrid.com>
 */

namespace Boldgrid\Library\Library\Plugin;

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
class Installer {

	/**
	 * @access protected
	 *
	 * @var array $configs   Configuration options for the plugin installer.
	 * @var array $transient Data from boldgrid_plugins transient.
	 */
	protected
		$configs,
		$releaseChannel,
		$transient;

	/**
	 * Initialize class and set class properties.
	 *
	 * @since 1.0.0
	 *
	 * @param array $configs Array of configuration options for the BG Library.
	 */
	public function __construct( $configs, Library\ReleaseChannel $releaseChannel ) {
		$this->setConfigs( $configs );
		$this->releaseChannel = $releaseChannel;
		if ( $this->configs['enabled'] && ! empty( $this->configs['plugins'] ) ) {
			$this->setPluginData( $this->configs['plugins'] );
			$this->setTransient();
			$license = new Library\License;
			$this->license = Library\Configs::get( 'licenseData' );
			$this->ajax();
			Library\Filter::add( $this );
		}
	}

	/**
	 * Set the configs class property.
	 *
	 * @since 1.0.0
	 *
	 * @param array $configs Array of configuration options for plugin installer.
	 *
	 * @return object $configs The configs class property.
	 */
	protected function setConfigs( $configs ) {
		return $this->configs = $configs;
	}

	/**
	 * Set the transient class property.
	 *
	 * @since 1.0.0
	 *
	 * @return object $transient The transient class property.
	 */
	protected function setTransient() {
		return $this->transient = get_site_transient( 'boldgrid_plugins', null ) ? get_site_transient( 'boldgrid_plugins' ) : $this->getPluginInformation( $this->configs['plugins'] );
	}

	/**
	 * Sets local plugin data for plugin configs to use in class.
	 *
	 * @since 1.0.0
	 *
	 * @param array $plugins Array of plugins to get data for.
	 */
	protected function setPluginData( $plugins ) {

		// Load plugin.php if necessary so method doesn't cause errors.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Default data to use if plugin isn't loaded locally.
		$data = array(
			'Author' => 'BoldGrid.com',
			'Version' => null,
		);

		foreach( $plugins as $plugin => $details ) {
			$file = trailingslashit( WP_PLUGIN_DIR ) . $details['file'];

			if ( file_exists( $file ) && is_readable( $file ) ) {
				$data = get_plugin_data( $file, false );
			}

			$this->configs['plugins'][ $plugin ] = wp_parse_args( $data, $details );
		}
	}

	/**
	 * Add "BoldGrid" tab to the plugins install filter bar.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tabs Tabs in the WordPress "Plugins > Add New" filter bar.
	 *
	 * @hook: install_plugins_tabs
	 *
	 * @return array $tabs Tabs in the WordPress "Plugins > Add New" filter bar.
	 */
	public function addTab( $tabs ) {
		$boldgrid = array( 'boldgrid' => __( 'BoldGrid', 'boldgrid-library' ) );
		$tabs = array_merge( $boldgrid, $tabs );

		return $tabs;
	}

	/**
	 * Filter the Plugin API arguments.
	 *
	 * We use this filter to add the custom "boldgrid" field for the
	 * "browse" arg to the WordPress Plugin API.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $args   WordPress Plugins API arguments.
	 * @param string $action The type of information being requested from the Plugin Install API.
	 *
	 * @hook: plugins_api_args
	 *
	 * @return array $args WordPress Plugins API arguments.
	 */
	public function pluginsApiArgs( $args, $action ) {
		$boldgrid_plugins = get_site_transient( 'boldgrid_plugins' );
		if ( isset( $args->slug ) ) {
			$plugin = $args->slug;
			if ( isset( $boldgrid_plugins->$plugin ) ) {
				$args->browse = 'boldgrid';
			}
		}

		return $args;
	}

	/**
	 * Filter the WordPress Plugins API information before we display it.
	 *
	 * We use this filter to add data to the WordPress Plugins API containing
	 * the information necessary for our outside sources to be installed.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $results The results object or array. Default is false false.
	 * @param string $action  The type of information being requested from the Plugin Install API.
	 * @param array  $args    WordPress Plugins API arguments.
	 *
	 * @hook: plugins_api
	 *
	 * @return array $args WordPress Plugins API arguments.
	 */
	public function pluginsApi( $results, $action, $args ) {
		$boldgrid_plugins = $this->getTransient() ? : array();

		// Check if we are hooked to query_plugins and browsing 'boldgrid' sorted plugins.
		if ( isset( $args->browse ) && $args->browse === 'boldgrid' ) {

			// Query plugins action.
			if ( $action === 'query_plugins' ) {
				$results = new \stdClass();

				// Set the results for query.
				$results->plugins = array_values( ( array ) $boldgrid_plugins );
				$results->info = array( 'results' => count( $results->plugins ) );

			// The plugin-information tab expects a different format.
			} elseif ( $action === 'plugin_information' ) {
				if ( ! empty( $boldgrid_plugins->{$args->slug} ) ) {
					$results = $boldgrid_plugins->{$args->slug};
				}
			}
		}

		return $results;
	}

	/**
	 * Filter the Plugin API results.
	 *
	 * We use this filter to add our plugins to the plugins_api results.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $result Plugins API result.
	 * @param array  $args   WordPress Plugins API arguments.
	 * @param string $action The type of information being requested from the Plugin Install API.
	 *
	 * @hook: plugins_api_result
	 *
	 * @return array $result WordPress Plugins API result.
	 */
	public function result( $result, $action, $args ) {
		$this->getTransient() ? : array();

		// Add data for plugin info tabs in results.
		if ( $action === 'plugin_information' ) {
			if ( ! empty( $boldgrid_plugins->{$args->slug} ) ) {
				unset( $boldgrid_plugins->{$args->slug}->active_installs );
				$result = $boldgrid_plugins->{$args->slug};
			}

		// Allow BoldGrid ( + variations of ) as a search option to show all plugins.
		} else if ( $action === 'query_plugins' ) {
			if ( ! empty( $args->search ) && strpos( strtolower( $args->search ), 'boldgrid' ) !== false ) {

				// Add all boldgrid plugins.
				$result->plugins = array_merge( ( array ) $boldgrid_plugins, ( array ) $result->plugins );

				// Count results found.
				$result->info['results'] = count( ( array ) $result->plugins );
			} else if ( ! empty( $args->search ) ) {
				$found = array();
				foreach ( $boldgrid_plugins as $plugin ) {
					if ( strpos( strtolower( implode( ' ', $plugin->tags ) ), trim( strtolower( $args->search ) ) ) !== false ) {
						$found[] = $plugin;
					}
				}

				// Merge found results.
				$result->plugins = array_merge( $found, ( array ) $result->plugins );

				// Recount the results found.
				$result->info['results'] = ( $result->info['results'] + count( $found ) );
			}
		}

		return $result;
	}

	/**
	 * Initialize the display of the plugins.
	 *
	 * @since 1.0.0
	 *
	 * @hook: install_plugins_boldgrid
	 */
	public function init() {
		$plugins = $this->getTransient();
		// Abort if we don't have any plugins to list.
		if ( ! $plugins ) {
			return;
		}

		?>
		<div class="bglib-plugin-installer">
		<?php
			foreach ( $plugins as $api ) {
				$button_classes = 'install button';
				$button_text = __( 'Install Now', 'boldgrid-library' );

					// Main plugin file.
					$file = $this->configs['plugins'][ $api->slug ]['file'];
					if ( $this->getPluginFile( $api->slug ) ) {

						// Has activation already occured? Disable button if so.
						if ( is_plugin_active( $file ) ) {
							$button_classes = 'button disabled';
							$button_text = __( 'Activated', 'boldgrid-library' );

						// Otherwise allow activation button.
						} else {
							$button_classes = 'activate button button-primary';
							$button_text = __( 'Activate', 'boldgrid-library' );
						}
					}

					$button_link = add_query_arg(
						array(
							'action' => 'install-plugin',
							'plugin' => $api->slug,
							'_wpnonce' => wp_create_nonce( "install-plugin_{$api->slug}" ),
						),
						self_admin_url( 'update.php' )
					);

					$button = array(
						'link' => $button_link,
						'classes' => $button_classes,
						'text' => $button_text,
					);

					$modal = add_query_arg(
						array(
							'tab' => 'plugin-information',
							'plugin' => $api->slug,
							'TB_iframe' => 'true',
						),
						self_admin_url( 'plugin-install.php' )
					);

					// Plugin Name ( Consists of name plus version number e.g. BoldGrid Inspirations 1.4 ).
					$name = "$api->name $api->version";

					$premiumSlug = $api->slug . '-premium';
					$pluginClasses = $api->slug;

					$premiumLink = '';
					$premiumUrl = $this->getPremiumUrl();

					if ( isset( $this->license->{$premiumSlug} ) || isset( $this->license->{$api->slug} ) ) {
						$pluginClasses = "plugin-card-{$api->slug} premium";
					} else {
						$premiumLink = '<li><a href="' . $premiumUrl . '" class="button get-premium" target="_blank" aria-label="' . sprintf( __( 'Upgrade %s to premium', 'boldgrid-library' ), $api->name ) . '">' . sprintf( __( 'Get Premium!' ), 'boldgrid-library' ) . '</a></li>';
					}

					$messageClasses = 'installer-messages';
					$message = '';
					if ( ( $this->configs['plugins'][ $api->slug ]['Version'] !== $api->new_version ) && $this->getPluginFile( $api->slug ) ) {
						$messageClasses = "{$messageClasses} update-message notice inline notice-warning notice-alt";
						$updateUrl = add_query_arg(
							array(
								'action' => 'upgrade-plugin',
								'plugin' => urlencode( $file ),
								'slug' => $api->slug,
								'_wpnonce' => wp_create_nonce( "upgrade-plugin_{$api->slug}" ),
							),
							self_admin_url( 'update.php' )
						);
						$updateLink = '<a href="' . $updateUrl . '" class="update-link" aria-label="' . sprintf( __( 'Update %s now', 'boldgrid-library' ), $api->name ) . '" data-plugin="' . $file . '" data-slug="' . $api->slug . '"> ' . __( 'Update now' ) . '</a>';
						$message = sprintf( __( 'New version available. %s' ), $updateLink );
					}

					// Send plugin data to template.
					$this->renderTemplate( $pluginClasses, $message, $messageClasses, $api, $name, $button, $modal, $premiumLink );
				}
			?>
		</div>
		<?php
	}


	/**
	 * Adds ajax actions for plugin installer page.
	 *
	 * @since 1.0.0
	 */
	protected function ajax() {
		$activate = new Installer\Activate( $this->configs );
		$activate->init();
	}

	/**
	 * Helper to get and verify the plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $slug Slug of the plugin to get main file for.
	 *
	 * @return mixed  $file Main plugin file of slug or null if not found.
	 */
	public function getPluginFile( $slug ) {

		// Load plugin.php if not already included by core.
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		$plugins = get_plugins();
		foreach ( $plugins as $file => $info ) {

			// Get the basename of the plugin.
			$basename = dirname( plugin_basename( $file ) );
			if ( $basename === $slug ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Get the premium URL for a users to login and upgrade with.
	 *
	 * This checks the 'boldgrid_reseller' option to see if an
	 * Account Management Panel link has been saved for a user's
	 * key, so they can upgrade that way.
	 *
	 * @since 1.0.0
	 *
	 * @return string $url The url for a user to login to upgrade through.
	 */
	private function getPremiumUrl() {
		$option = get_site_option( 'boldgrid_reseller' );
		$url = 'https://www.boldgrid.com/connect-keys/';
		if ( $option && ! empty( $option['reseller_amp_url'] ) ) {
			$url = $option['reseller_amp_url'];
		}

		return $url;
	}

	/**
	 * Renders template for each plugin card.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $plugin         Original data passed in for plugin list.
	 * @param string $pluginClasses  Classes to add for plugin card.
	 * @param string $message        The message to display if any.
	 * @param string $messageClasses Classes to apply to the message box.
	 * @param array  $api            Results from plugins_api
	 * @param string $name           Plugin name/version used for aria labels.
	 * @param array  $button         Contains button link, text and classes.
	 * @param string $modal          Modal link for thickbox plugin-information tabs.
	 */
	public function renderTemplate( $pluginClasses, $message, $messageClasses, $api, $name, $button, $modal, $premiumLink ) {
		include Library\Configs::get( 'libraryDir' ) . 'src/Library/Views/PluginInstaller.php';
	}

	/**
	 * Enqueue required CSS, JS, and localization for installer.
	 *
	 * @since 1.0.0
	 *
	 * @hook: admin_enqueue_scripts
	 */
	public function enqueue( $filter ) {
		$this->css( $filter );
		$this->js( $filter );
	}

	/**
	 * CSS to load for functionality.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $filter pagenow.
	 */
	public function css( $filter ) {
		if ( $filter === 'plugin-install.php' && ( ! isset( $_GET['tab'] ) || isset( $_GET['tab'] ) && ( $_GET['tab'] === 'boldgrid' || $_GET['tab'] === 'plugin-information' ) ) ) {
			wp_register_style(
				'bglib-plugin-installer',
				Library\Configs::get( 'libraryUrl' ) .  'src/assets/css/plugin-installer.css',
				array( 'common' )
			);

			wp_enqueue_style( 'bglib-plugin-installer' );
		}
	}

	/**
	 * JS to load for functionality.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $filter pagenow.
	 */
	public function js( $filter ) {
		if ( $filter === 'plugin-install.php' && ( ! isset( $_GET['tab'] ) || ( isset( $_GET['tab'] ) && $_GET['tab'] === 'boldgrid' ) ) ) {
			// Enqueue Javascript.
			wp_register_script(
				'bglib-plugin-installer',
				Library\Configs::get( 'libraryUrl' ) . 'src/assets/js/plugin-installer.js',
				array(
					'jquery',
					'plugin-install',
					'updates',
				)
			);

			// Add localized variables.
			wp_localize_script(
				'bglib-plugin-installer',
				'_bglibPluginInstaller',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'bglibPluginInstallerNonce' ),
					'status' => ( bool ) $this->getTransient(),
					'install' => __( 'Install Now', 'boldgrid-library' ),
					'installing' => __( 'Installing', 'boldgrid-library' ),
					'installed' => __( 'Activated', 'boldgrid-library' ),
					'activate' => __( 'Activate', 'boldgrid-library' ),
					'activating' => __( 'Activating', 'boldgrid-library' ),
				)
			);

			// Send over update data.
			wp_localize_script(
				'updates',
				'_wpUpdatesItemCounts',
				array(
					'totals' => wp_get_update_data(),
				)
			);

			wp_enqueue_script( 'updates' );
			wp_enqueue_script( 'bglib-plugin-installer' );
		}
	}

	/**
	 * Hides inaccurate info on plugin search results loaded from
	 * external sources.
	 *
	 * @since 1.0.0
	 *
	 * @hook: admin_head-plugin-install.php
	 */
	public function hideInfo( $array ) {

		$css = '<style>';

		foreach ( $this->configs['plugins'] as $plugin => $details ) {

			// Feedback/rating details.
			$css .= ".plugin-card-{$plugin} .vers.column-rating{display:none;}";

			// Active install counts.
			$css .= ".plugin-card-{$plugin} .column-downloaded{display:none;}";
		}

		$css .= '</style>';

		echo $css;
		return $array;
	}

	/**
	 * Prepares the data for the WordPress Plugins API.
	 *
	 * @return [type] [description]
	 */
	public function getPluginInformation( $plugins ) {
		global $wp_version;

		// Get the API URL and Endpoint to call for requests.
		$api = Library\Configs::get( 'api' );
		$endpoint = '/api/open/getPluginVersion';

		// Each call will be stored in the $responses object before saving transient.
		$responses = new \stdClass();

		// Loop through plugins and save the data to transient.
		foreach ( $plugins as $plugin => $details ) {

			// Params to pass to remote API call.
			$params = array(
				'key' => $details['key'],
				'channel' => $this->releaseChannel->getPluginChannel(),
				'installed_' . $details['key'] . '_version' => $details['Version'],
				'installed_wp_version' => $wp_version,
			);

			// Make API Call.
			$call = new Library\Api\Call( $api . $endpoint, $params );

			if ( ! $call->getError() ) {

				// Add the actual data to our $responses object.
				$responses->{$plugin} = $call->getResponse()->result->data;

				// Newest version of plugin (?).
				$responses->{$plugin}->new_version = $responses->{$plugin}->version;

				// Name is the expected value for the plugins_api, but we are returned title.
				$responses->{$plugin}->name = $responses->{$plugin}->title;

				// Slug is never returned from remote call, so we will create slug based on the title.
				$responses->{$plugin}->slug = sanitize_title( $responses->{$plugin}->title );

				// This was in the update class, but with/without it - it seemed to work.  Not sure if this
				// is really necessary to do as the data should be prepared on the asset server first.
				$responses->{$plugin}->sections = preg_replace(
					'/\s+/', ' ',
					trim( $responses->{$plugin}->sections )
				);

				$responses->{$plugin}->sections = json_decode( $responses->{$plugin}->sections, true );

				// Decode tags for searches.
				$responses->{$plugin}->tags = json_decode( $responses->{$plugin}->tags, true );

				// Loop through the decoded sections and then add to sections array.
				foreach ( $responses->{$plugin}->sections as $section => $section_data ) {
					$responses->{$plugin}->sections[ $section ] = html_entity_decode(
						preg_replace( '/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/', "\n", $section_data ), ENT_QUOTES
					);
				}

				// Set the short_description as the description before preparing.
				$responses->{$plugin}->short_description = $responses->{$plugin}->sections['description'];

				// Now generate the short_description from the standard description automatically.
				if ( mb_strlen( $responses->{$plugin}->short_description ) > 150 ) {
					$responses->{$plugin}->short_description = mb_substr( $responses->{$plugin}->short_description, 0, 150 ) . ' &hellip;';

					// If not a full sentence, and one ends within 20% of the end, trim it to that.
					if ( function_exists( 'mb_strrpos' ) ) {
						$pos = mb_strrpos( $responses->{$plugin}->short_description, '.' );
					} else {
						$pos = strrpos( $responses->{$plugin}->short_description, '.' );
					}
					if ( '.' !== mb_substr( $responses->{$plugin}->short_description, - 1 ) && $pos > ( 0.8 * 150 ) ) {
						$responses->{$plugin}->short_description = mb_substr( $responses->{$plugin}->short_description, 0, $pos + 1 );
					}
				}

				// Remove any remaining markup from short_description.
				$responses->{$plugin}->short_description = wp_strip_all_tags( $responses->{$plugin}->short_description );

				// Returned release date === last_updated (?)
				$responses->{$plugin}->last_updated = $responses->{$plugin}->release_date;

				// Create the author URL based on the siteurl and author name of plugin library is being ran in.
				$responses->{$plugin}->author = '<a href="' . $responses->{$plugin}->siteurl . '" target="_blank">' . $details['Author'] . '</a>';

				// This has to be json decoded since this array is json encoded for whatever reason.
				$responses->{$plugin}->banners = json_decode( $responses->{$plugin}->banners, true );

				// Just creating the links by having the file naming standardized.  WordPress also expects this same
				// format for these files.  The same approach can be taken for the banners as well.
				$responses->{$plugin}->icons = array(
					'1x' => "https://repo.boldgrid.com/assets/icon-{$responses->{$plugin}->slug}-128x128.png",
					'2x' => "https://repo.boldgrid.com/assets/icon-{$responses->{$plugin}->slug}-256x256.png",
					'svg' => "https://repo.boldgrid.com/assets/icon-{$responses->{$plugin}->slug}-128x128.svg",
				);

				// This seems hardcoded in based on looking at our plugins in the update class.
				$responses->{$plugin}->added = '2015-03-19';

				// Setting the returned siteurl as the expected url param.
				$responses->{$plugin}->url = $responses->{$plugin}->siteurl;
				$responses->{$plugin}->active_installs = true;

				// Build the URL for the plugin download from the asset server.
				$responses->{$plugin}->download_link = add_query_arg(
					array(
						'key' => Library\Configs::get( 'key' ),
						'id' => $responses->{$plugin}->asset_id,
						'installed_plugin_version' => $details['Version'],
						'installed_wp_version' => $wp_version,
					),
					$api . '/api/asset/get'
				);
			}
		}

		// Update transient.  Expiry set to 1 week.
		set_site_transient( 'boldgrid_plugins', $responses, 7 * DAY_IN_SECONDS );
	}


	/**
	 * Filters the WordPress Updates Available.
	 *
	 * This is set to priority 12 to override the individual plugin
	 * update classes that set priority at 11 in this filter.
	 *
	 * @since 1.0.0
	 *
	 * @hook: pre_set_site_transient_update_plugins
	 * @hook: site_transient_update_plugins
	 *
	 * @return object $updates Updates available.
	 */
	public function filterUpdates( $updates ) {
		$plugins = $this->getTransient() ? : array();

		foreach( $plugins as $plugin => $details ) {
			$update = new \stdClass();
			$update->plugin = $this->configs['plugins'][ $plugin ]['file'];
			$update->slug = $details->slug;
			$update->new_version = $details->new_version;
			$update->url = $details->url;
			$update->package = $details->download_link;

			if ( ( $this->configs['plugins'][ $plugin ]['Version'] !== $details->new_version ) && $this->getPluginFile( $details->slug ) ) {
				$update->tested = $details->tested_wp_version;
				$update->compatibility = new \stdClass();
				$updates->response[ $update->plugin ] = $update;
			} else {
				$updates->no_update[ $update->plugin ] = $update;
			}
		}

		return $updates;
	}

	/**
	 * Modify Update Class Hooks.
	 *
	 * @since 1.0.0
	 *
	 * @hook: admin_init
	 */
	public function modifyUpdate() {
		$plugins = $this->getTransient() ? : array();

		foreach( $plugins as $plugin => $details ) {
			$p = explode( '-', $plugin );
			$p = array_map( 'ucfirst', $p );
			$p = implode( '_', $p );
			$class = $p . '_Update';
			if ( class_exists( $class ) ) {
				Library\Filter::removeHook( 'plugins_api', $class, 'custom_plugins_transient_update', 11 );
				Library\Filter::removeHook( 'custom_plugins_transient_update', $class, 'custom_plugins_transient_update', 11 );
				Library\Filter::removeHook( 'pre_set_site_transient_update_plugins', $class, 'custom_plugins_transient_update', 11 );
				Library\Filter::removeHook( 'site_transient_update_plugins', $class, 'site_transient_update_plugins', 11 );
				delete_site_transient( "{$p}_version_data" );
			}
		}
	}

	/**
	 * Gets configs class property.
	 *
	 * @since  1.0.0
	 *
	 * @return array $configs The configs class property.
	 */
	protected function getConfigs() {
		return $this->configs;
	}

	/**
	 * Gets the transient class property.
	 *
	 * @since  1.0.0
	 *
	 * @nohook
	 *
	 * @return mixed $transient The transient class property.
	 */
	public function getTransient() {
		return $this->transient;
	}
}
