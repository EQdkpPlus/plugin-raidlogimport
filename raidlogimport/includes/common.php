<?php
 /*
 * Project:     EQdkp-Plus Raidlogimport
 * License:     Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:       2008
 * Date:        $Date$
 * -----------------------------------------------------------------------
 * @author      $Author$
 * @copyright   2008-2009 hoofy_leon
 * @link        http://eqdkp-plus.com
 * @package     raidlogimport
 * @version     $Rev$
 *
 * $Id$
 */

if(!defined('EQDKP_INC'))
{
	header('HTTP/1.0 Not Found');
	exit;
}

include_once($eqdkp_root_path.'common.php');
if(!version_compare(phpversion(), '5.1.2', '>='))
{
    message_die('This Plugin needs at least PHP-Version 5.1.2. Your Version is: '.phpversion().'.');
}
if (!registry::register('plugin_manager')->check('raidlogimport', PLUGIN_INSTALLED) ) {
    message_die('The Raid-Log-Import plugin is not installed.');
}
require_once($eqdkp_root_path.'plugins/raidlogimport/includes/functions.php');
require_once($eqdkp_root_path.'plugins/raidlogimport/includes/rli_adjustment.class.php');
require_once($eqdkp_root_path.'plugins/raidlogimport/includes/rli_item.class.php');
require_once($eqdkp_root_path.'plugins/raidlogimport/includes/rli_member.class.php');
require_once($eqdkp_root_path.'plugins/raidlogimport/includes/rli_parse.class.php');
require_once($eqdkp_root_path.'plugins/raidlogimport/includes/rli_raid.class.php');
require_once($eqdkp_root_path.'plugins/raidlogimport/includes/rli.class.php');
?>