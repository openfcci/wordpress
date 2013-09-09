<?php
/**
 * WordPress Administration Update API
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Selects the first update version from the update_core option
 *
 * @return object the response from the API
 */
function get_preferred_from_update_core() {
	$updates = get_core_updates();
	if ( ! is_array( $updates ) )
		return false;
	if ( empty( $updates ) )
		return (object) array( 'response' => 'latest' );
	return $updates[0];
}

/**
 * Get available core updates
 *
 * @param array $options Set $options['dismissed'] to true to show dismissed upgrades too,
 * 	set $options['available'] to false to skip not-dismissed updates.
 * @return array Array of the update objects
 */
function get_core_updates( $options = array() ) {
	$options = array_merge( array( 'available' => true, 'dismissed' => false ), $options );
	$dismissed = get_site_option( 'dismissed_update_core' );

	if ( ! is_array( $dismissed ) )
		$dismissed = array();

	$from_api = get_site_transient( 'update_core' );

	if ( ! isset( $from_api->updates ) || ! is_array( $from_api->updates ) )
		return false;

	$updates = $from_api->updates;
	$result = array();
	foreach ( $updates as $update ) {
		if ( array_key_exists( $update->current . '|' . $update->locale, $dismissed ) ) {
			if ( $options['dismissed'] ) {
				$update->dismissed = true;
				$result[] = $update;
			}
		} else {
			if ( $options['available'] ) {
				$update->dismissed = false;
				$result[] = $update;
			}
		}
	}
	return $result;
}

function dismiss_core_update( $update ) {
	$dismissed = get_site_option( 'dismissed_update_core' );
	$dismissed[ $update->current . '|' . $update->locale ] = true;
	return update_site_option( 'dismissed_update_core', $dismissed );
}

function undismiss_core_update( $version, $locale ) {
	$dismissed = get_site_option( 'dismissed_update_core' );
	$key = $version . '|' . $locale;

	if ( ! isset( $dismissed[$key] ) )
		return false;

	unset( $dismissed[$key] );
	return update_site_option( 'dismissed_update_core', $dismissed );
}

function find_core_update( $version, $locale ) {
	$from_api = get_site_transient( 'update_core' );

	if ( ! isset( $from_api->updates ) || ! is_array( $from_api->updates ) )
		return false;

	$updates = $from_api->updates;
	foreach ( $updates as $update ) {
		if ( $update->current == $version && $update->locale == $locale )
			return $update;
	}
	return false;
}

function core_update_footer( $msg = '' ) {
	if ( !current_user_can('update_core') )
		return sprintf( __( 'Version %s' ), get_bloginfo( 'version', 'display' ) );

	$cur = get_preferred_from_update_core();
	if ( ! is_object( $cur ) )
		$cur = new stdClass;

	if ( ! isset( $cur->current ) )
		$cur->current = '';

	if ( ! isset( $cur->url ) )
		$cur->url = '';

	if ( ! isset( $cur->response ) )
		$cur->response = '';

	switch ( $cur->response ) {
	case 'development' :
		return sprintf( __( 'You are using a development version (%1$s). Cool! Please <a href="%2$s">stay updated</a>.' ), get_bloginfo( 'version', 'display' ), network_admin_url( 'update-core.php' ) );
	break;

	case 'upgrade' :
		return sprintf( '<strong>'.__( '<a href="%1$s">Get Version %2$s</a>' ).'</strong>', network_admin_url( 'update-core.php' ), $cur->current);
	break;

	case 'latest' :
	default :
		return sprintf( __( 'Version %s' ), get_bloginfo( 'version', 'display' ) );
	break;
	}
}
add_filter( 'update_footer', 'core_update_footer' );

function update_nag() {
	if ( is_multisite() && !current_user_can('update_core') )
		return false;

	global $pagenow;

	if ( 'update-core.php' == $pagenow )
		return;

	$cur = get_preferred_from_update_core();

	if ( ! isset( $cur->response ) || $cur->response != 'upgrade' )
		return false;

	if ( current_user_can('update_core') ) {
		$msg = sprintf( __('<a href="http://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> is available! <a href="%2$s">Please update now</a>.'), $cur->current, network_admin_url( 'update-core.php' ) );
	} else {
		$msg = sprintf( __('<a href="http://codex.wordpress.org/Version_%1$s">WordPress %1$s</a> is available! Please notify the site administrator.'), $cur->current );
	}
	echo "<div class='update-nag'>$msg</div>";
}
add_action( 'admin_notices', 'update_nag', 3 );
add_action( 'network_admin_notices', 'update_nag', 3 );

// Called directly from dashboard
function update_right_now_message() {
	$msg = sprintf( __( 'You are using <span class="b">WordPress %s</span>.' ), get_bloginfo( 'version', 'display' ) );

	if ( current_user_can('update_core') ) {
		$cur = get_preferred_from_update_core();

		if ( isset( $cur->response ) && $cur->response == 'upgrade' )
			$msg .= " <a href='" . network_admin_url( 'update-core.php' ) . "' class='button'>" . sprintf( __('Update to %s'), $cur->current ? $cur->current : __( 'Latest' ) ) . '</a>';
	}

	echo "<span id='wp-version-message'>$msg</span>";
}

function get_plugin_updates() {
	$all_plugins = get_plugins();
	$upgrade_plugins = array();
	$current = get_site_transient( 'update_plugins' );
	foreach ( (array)$all_plugins as $plugin_file => $plugin_data) {
		if ( isset( $current->response[ $plugin_file ] ) ) {
			$upgrade_plugins[ $plugin_file ] = (object) $plugin_data;
			$upgrade_plugins[ $plugin_file ]->update = $current->response[ $plugin_file ];
		}
	}

	return $upgrade_plugins;
}

function wp_plugin_update_rows() {
	if ( !current_user_can('update_plugins' ) )
		return;

	$plugins = get_site_transient( 'update_plugins' );
	if ( isset($plugins->response) && is_array($plugins->response) ) {
		$plugins = array_keys( $plugins->response );
		foreach( $plugins as $plugin_file ) {
			add_action( "after_plugin_row_$plugin_file", 'wp_plugin_update_row', 10, 2 );
		}
	}
}
add_action( 'admin_init', 'wp_plugin_update_rows' );

function wp_plugin_update_row( $file, $plugin_data ) {
	$current = get_site_transient( 'update_plugins' );
	if ( !isset( $current->response[ $file ] ) )
		return false;

	$r = $current->response[ $file ];

	$plugins_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
	$plugin_name = wp_kses( $plugin_data['Name'], $plugins_allowedtags );

	$details_url = self_admin_url('plugin-install.php?tab=plugin-information&plugin=' . $r->slug . '&section=changelog&TB_iframe=true&width=600&height=800');

	$wp_list_table = _get_list_table('WP_Plugins_List_Table');

	if ( is_network_admin() || !is_multisite() ) {
		echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message">';

		if ( ! current_user_can('update_plugins') )
			printf( __('There is a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a>.'), $plugin_name, esc_url($details_url), esc_attr($plugin_name), $r->new_version );
		else if ( empty($r->package) )
			printf( __('There is a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a>. <em>Automatic update is unavailable for this plugin.</em>'), $plugin_name, esc_url($details_url), esc_attr($plugin_name), $r->new_version );
		else
			printf( __('There is a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a> or <a href="%5$s">update now</a>.'), $plugin_name, esc_url($details_url), esc_attr($plugin_name), $r->new_version, wp_nonce_url( self_admin_url('update.php?action=upgrade-plugin&plugin=') . $file, 'upgrade-plugin_' . $file) );

		do_action( "in_plugin_update_message-$file", $plugin_data, $r );

		echo '</div></td></tr>';
	}
}

function get_theme_updates() {
	$themes = wp_get_themes();
	$current = get_site_transient('update_themes');

	if ( ! isset( $current->response ) )
		return array();

	$update_themes = array();
	foreach ( $current->response as $stylesheet => $data ) {
		$update_themes[ $stylesheet ] = wp_get_theme( $stylesheet );
		$update_themes[ $stylesheet ]->update = $data;
	}

	return $update_themes;
}

function wp_theme_update_rows() {
	if ( !current_user_can('update_themes' ) )
		return;

	$themes = get_site_transient( 'update_themes' );
	if ( isset($themes->response) && is_array($themes->response) ) {
		$themes = array_keys( $themes->response );

		foreach( $themes as $theme ) {
			add_action( "after_theme_row_$theme", 'wp_theme_update_row', 10, 2 );
		}
	}
}
add_action( 'admin_init', 'wp_theme_update_rows' );

function wp_theme_update_row( $theme_key, $theme ) {
	$current = get_site_transient( 'update_themes' );
	if ( !isset( $current->response[ $theme_key ] ) )
		return false;
	$r = $current->response[ $theme_key ];
	$themes_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
	$theme_name = wp_kses( $theme['Name'], $themes_allowedtags );

	$details_url = add_query_arg( array( 'TB_iframe' => 'true', 'width' => 1024, 'height' => 800 ), $current->response[ $theme_key ]['url'] );

	$wp_list_table = _get_list_table('WP_MS_Themes_List_Table');

	echo '<tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange"><div class="update-message">';
	if ( ! current_user_can('update_themes') )
		printf( __('There is a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a>.'), $theme['Name'], esc_url($details_url), esc_attr($theme['Name']), $r->new_version );
	else if ( empty( $r['package'] ) )
		printf( __('There is a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a>. <em>Automatic update is unavailable for this theme.</em>'), $theme['Name'], esc_url($details_url), esc_attr($theme['Name']), $r['new_version'] );
	else
		printf( __('There is a new version of %1$s available. <a href="%2$s" class="thickbox" title="%3$s">View version %4$s details</a> or <a href="%5$s">update now</a>.'), $theme['Name'], esc_url($details_url), esc_attr($theme['Name']), $r['new_version'], wp_nonce_url( self_admin_url('update.php?action=upgrade-theme&theme=') . $theme_key, 'upgrade-theme_' . $theme_key) );

	do_action( "in_theme_update_message-$theme_key", $theme, $r );

	echo '</div></td></tr>';
}

function maintenance_nag() {
	global $upgrading;
	if ( ! isset( $upgrading ) )
		return false;

	if ( current_user_can('update_core') )
		$msg = sprintf( __('An automated WordPress update has failed to complete - <a href="%s">please attempt the update again now</a>.'), 'update-core.php' );
	else
		$msg = __('An automated WordPress update has failed to complete! Please notify the site administrator.');

	echo "<div class='update-nag'>$msg</div>";
}
add_action( 'admin_notices', 'maintenance_nag' );
