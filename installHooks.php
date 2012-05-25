<?php 
/**
 * Quote and Split (qas)
 *
 * @package qas
 * @author emanuele
 * @copyright 2012 emanuele, Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 0.1.0
 */

// If we have found SSI.php and we are outside of SMF, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) // If we are outside SMF and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');
  
$integration_function = empty($context['uninstalling']) ? 'add_integration_function' : 'remove_integration_function';

$integration_function('integrate_admin_include', '$sourcedir/Subs-QuoteAndSplit.php');
$integration_function('integrate_general_mod_settings', 'qas_add_modsettings');

?>