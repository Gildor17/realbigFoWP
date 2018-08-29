<?php

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
include_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
include ( ABSPATH . "wp-content/plugins/realbigForWP/update.php");
include ( ABSPATH . "wp-content/plugins/realbigForWP/synchronising.php");

/**
 * Created by PhpStorm.
 * User: user
 * Date: 2018-07-03
 * Time: 10:34
 */

/*
Plugin name:  Realbig For WordPress
Description:  Реалбиговский плагин для вордпреса. Для полного описания перейдите по ссылке: <a href="https://github.com/Gildor17/realbigFoWP/blob/master/README.MD" target="_blank">https://github.com/Gildor17/realbigFoWP/blob/master/README.MD</a>
Version:      0.1.21.1a
Author:       Gildor
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

try
{
	/** **************************************************************************************************************** **/
	global $wpdb;
	global $table_prefix;

//	wp_redirect(get_site_url().'/wp-admin/index.php');  // this thing calling error

	/***************** updater code ***************************************************************************************/
	require 'plugin-update-checker/plugin-update-checker.php';
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/Gildor17/realbigForWP',
		__FILE__,
		'realbigForWP'
	);
	/****************** end of updater code *******************************************************************************/
	$GLOBALS['realbigForWP_version'] = '0.1.21.1a';
	/********** checking and creating tables ******************************************************************************/
	$wpPrefix = $table_prefix;
	if ( empty( $wpPrefix ) )
	{
	    $wpPrefix = $wpdb->base_prefix;
	}
	try
    {
		$tableForCurrentPluginChecker = $wpdb->get_var( 'SHOW TABLES LIKE "' . $wpPrefix . 'realbig_plugin_settings"' );   //settings for block table checking
		$tableForToken                = $wpdb->get_var( 'SHOW TABLES LIKE "' . $wpPrefix . 'realbig_settings"' );      //settings for token and other
//	$pluginActivityChecker        = is_plugin_active( 'realbigForWP/realbigForWP.php' );     //plugin status (active or not)
	}
	catch ( Exception $e )
    {
		echo $e;
	}

	dbTablesCreateFunction( $tableForCurrentPluginChecker, $tableForToken, $wpPrefix );
	dbOldTablesRemoveFunction( $wpPrefix );
	/********** end of checking and creating tables ***********************************************************************/

	$token                 = tokenChecking( $wpPrefix );
	$lastSyncTimeTransient = get_transient('realbigPluginSyncAttempt');

//	/*** enumUpdate */ $resultEnumUpdate = updateElementEnumValuesFunction(); /** enumUpdateEnd */
	/** enumUpdate */ $resultEnumUpdate = updateElementEnumValuesFunction(); /** enumUpdateEnd */

	if (!empty($resultEnumUpdate)&&$resultEnumUpdate == true)   //doesn't ended
//	if (true)   //doesn't ended
	{
	    $file = __FILE__;
	    $countEnumFunctionReplace = 1;

		$fileContent = file_get_contents($file);
		$editedFileContent = preg_replace('~\/\*\* enumUpdate \*\/ \$resultEnumUpdate = updateElementEnumValuesFunction\(\)\; \/\*\* enumUpdateEnd \*\/~',
			'/** enumUpdate  $resultEnumUpdate = updateElementEnumValuesFunction(); enumUpdateEnd */', $fileContent, $countEnumFunctionReplace);
		if (!empty($editedFileContent)) {
			$tset25 = file_put_contents($file, $editedFileContent);
		} else {
			$tset25 = file_put_contents($file, $fileContent);
		}
		$resultEnumUpdate = false;
    }
	/****************** autosync ******************************************************************************************/
	$wpOptionsCheckerSyncTime = $wpdb->get_row( $wpdb->prepare( 'SELECT optionValue FROM ' . $wpPrefix . 'realbig_settings WHERE optionName = %s', [ "token_sync_time" ] ) );
	if ( ! empty( $token ) && $token != 'no token' && $lastSyncTimeTransient == false) {
		try {
//			$wpOptionsCheckerSyncTime = $wpdb->get_row( $wpdb->prepare( 'SELECT optionValue FROM ' . $wpPrefix . 'realbig_settings WHERE optionName = %s', [ "token_sync_time" ] ) );
//	    $syncIterations = $wpdb->get_var('SELECT optionValue FROM '.$wpPrefix.'realbig_settings WHERE optionName = "syncRequest"');
//	    $wpdb->update($wpPrefix.'realbig_settings', ['optionValue'=> $syncIterations + 1], ['optionName'=>'syncRequest']);
			if ( ! empty( $wpOptionsCheckerSyncTime ) ) {
				$lastSyncTime = get_object_vars( $wpOptionsCheckerSyncTime );
			} else {
				$lastSyncTime = null;
			}

			if ( ! empty( $lastSyncTime ) ) {
				$timeDif = time() - intval( $lastSyncTime['optionValue'] );
				if ( $timeDif > 300 ) {
					$sameTokenResult = true;
					synchronize( $token, $wpOptionsCheckerSyncTime, $sameTokenResult, $wpPrefix );
					tokenTimeUpdateChecking( $GLOBALS['token'], $wpPrefix );
				}
			}
		} catch ( Exception $e ) {
			echo $e;
		}
	}
	/****************** end autosync **************************************************************************************/

	/********** adding AD code in head area *******************************************************************************/
	add_action( 'wp_head', 'AD_func_add', 1 );
	function AD_func_add()
    {
		require_once( 'textEditing.php' );
		$headerParsingResult = headerADInsertor();
		if ( $headerParsingResult == true )
		{
			?>
            <script type="text/javascript"> rbConfig = {start: performance.now()}; </script>
            <script async="async" type="text/javascript" src="//any.realbig.media/rotator.min.js"></script>
			<?php
		}
	}

	/********** end of adding AD code in head area ************************************************************************/

//$blocksSettingsTableChecking = $wpdb->query('SELECT id FROM '.$wpPrefix.'realbig_plugin_settings');
	if ( strpos( $GLOBALS['PHP_SELF'], 'wp-admin' ) != false )
	{
		if ( ! empty( $_POST['tokenInput'] ) )
		{
			$sameTokenResult = false;
			synchronize( $_POST['tokenInput'], ( empty( $wpOptionsCheckerSyncTime ) ? null : $wpOptionsCheckerSyncTime ), $sameTokenResult, $wpPrefix );
//			deactivate_plugins(plugin_basename( __FILE__ ));
		}
		elseif ( $GLOBALS['token'] == 'no token' )
        {
	        $GLOBALS['tokenStatusMessage'] = 'Введите токен';
		}
		tokenTimeUpdateChecking( $GLOBALS['token'], $wpPrefix );
	}
	/************* blocks for text ****************************************************************************************/
	$fromDb = $wpdb->get_results( 'SELECT setting_type, `text`, element, directElement, elementPosition, elementPlace, firstPlace, elementCount, elementStep FROM ' . $wpPrefix . 'realbig_plugin_settings WGPS' );
	/************* end blocks for text ************************************************************************************/
	add_filter( 'the_content', 'pathToIcons', 102 );
	/********** using settings in texts ***********************************************************************************/
	function pathToIcons( $content ) {
		if (is_page()||is_single()||is_singular()) {
			$fromDb = $GLOBALS['fromDb'];
			require_once( 'textEditing.php' );
			$setNum  = 1;
			$content = addIcons( $fromDb, $content );
			return $content;
		} else {
			return $content;
		}
	}
	/*********** end of using settings in texts ***************************************************************************/
//function adminPagesTest() {
//    global $wpdb;
//    $adminChecker = $wpdb->get_var('SELECT optionValue FROM '.$wpPrefix.'realbig_settings WHERE optionName = "testAdminRow"');
//	$adminChecker = $adminChecker + 1;
//    $wpdb->update($wpPrefix.'realbig_settings', ['optionValue'=> $adminChecker], ['optionName'=>'testAdminRow']);
//}
	/*********** begin of token input area ********************************************************************************/
	function my_plugin_action_links( $links ) {
		$links = array_merge( array( '<a href="' . esc_url( admin_url( '/admin.php?page=realbigForWP%2FrealbigForWP.php' ) ) . '">' . __( 'Settings', 'textdomain' ) . '</a>' ), $links );

		return $links;
	}

	add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'my_plugin_action_links' );

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	if ( is_admin() ) {
		add_action( 'admin_menu', 'my_pl_settings_menu_create' );
	}
	function my_pl_settings_menu_create() {
	    if (strpos($_SERVER['REQUEST_URI'], 'page=realbigForWP'))
	    {
		    add_menu_page( 'Your code sending configuration', 'realBIG', 'administrator', __FILE__, 'TokenSync', get_site_url().'/wp-content/plugins/realbigForWP/assets/realbig_plugin_hover.png' );
        }
        else
        {
	        add_menu_page( 'Your code sending configuration', 'realBIG', 'administrator', __FILE__, 'TokenSync', get_site_url().'/wp-content/plugins/realbigForWP/assets/realbig_plugin_standart.png' );
        }
//		add_menu_page( 'Your code sending configuration', 'realBIG', 'administrator', __FILE__, 'TokenSync', get_site_url().'/wp-content/plugins/realbigForWP/assets/realbig_plugin_hover.png' );
		add_action( 'admin_init', 'register_mysettings' );
	}

	function register_mysettings() {
		register_setting( 'sending_zone', 'token_value_input' );
		register_setting( 'sending_zone', 'token_value_send' );
	}

	function TokenSync() {
		?>
        <div class="wrap col-md-12">
            <form method="post" name="tokenForm" id="tokenFormId">
                <label><span style="font-size: 16px">Токен</span><br/>
                    <input name="tokenInput" id="tokenInputId" value="<?= $GLOBALS['token'] ?>" style="min-width: 280px"
                           required>
                    <label style="font-size: 16px; margin-left: 10px; color: <?= $GLOBALS['statusColor'] ?> ">Время
                        последней синхронизации: <?= $GLOBALS['tokenTimeUpdate'] ?></label>
                </label>
				<?php submit_button( 'Синхронизировать', 'primary', 'saveTokenButton' ) ?>
				<?php if ( ! empty( $GLOBALS['tokenStatusMessage'] ) ): ?>
                    <div name="rezultDiv" style="font-size: 16px"><?= $GLOBALS['tokenStatusMessage'] ?></div>
				<?php endif; ?>
            </form>
            <br>
            <div>Надписи ниже нужны для тестировки</div>
            <div>Статус соединения
                1: <?= ( ! empty( $GLOBALS['connection_request_rezult_1'] ) ? $GLOBALS['connection_request_rezult_1'] : 'empty' ) ?></div>
            <div>Статус соединения
                общий: <?= ( ! empty( $GLOBALS['connection_request_rezult'] ) ? $GLOBALS['connection_request_rezult'] : 'empty' ) ?></div>
<!--            <div>Ping-->
<!--                ping: --><?//= ( ! empty( $GLOBALS['shellResult'] ) ? $GLOBALS['shellResult'] : 'empty' ) ?><!--</div>-->
            <div>Ping
                ping: <?= ( ! empty( $GLOBALS['shellResult1'] ) ? $GLOBALS['shellResult1'] : 'empty' ) ?></div>
            <div>Ping
                ping: <?= ( ! empty( $GLOBALS['shellResult2'] ) ? $GLOBALS['shellResult2'] : 'empty' ) ?></div>
            <div>Ping
                ping: <?= ( ! empty( $GLOBALS['shellResult3'] ) ? $GLOBALS['shellResult3'] : 'empty' ) ?></div>
            <div>Ping
                ping: <?= ( ! empty( $GLOBALS['shellResult4'] ) ? $GLOBALS['shellResult4'] : 'empty' ) ?></div>
        </div>
		<?php
	}
	/************ end of token input area *********************************************************************************/
}
catch (Exception $ex)
{
//	deactivate_plugins('realbigForWP');
	deactivate_plugins(plugin_basename( __FILE__ ));
	?><div style="margin-left: 200px; border: 3px solid red"><? echo $ex; ?></div><?

//	wp_die( 'test' );
}
catch (Error $er)
{
	deactivate_plugins(plugin_basename( __FILE__ ));
    ?><div style="margin-left: 200px; border: 3px solid red"><? echo $er; ?></div><?

//    $urlForRedirection = get_site_url().'/wp-admin/index.php';
//	wp_safe_redirect($urlForRedirection, 302);  // this thing calling error
//    exit;
}