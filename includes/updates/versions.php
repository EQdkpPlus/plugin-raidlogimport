<?php

if(!defined('EQDKP_INC'))
{
	header('HTTP/1.0 404 Not Found');
	exit;
}
$up_updates = array(
	'0.4.0'	=> array(
		'file'	=> '030-040.php',
		'old'	=> '0.3.0'
	),
	'0.4.0.1' => array(
		'file'	=> '040-0401.php',
		'old'	=> '0.4.0'
	),
	'0.4.2'	=> array(
		'file'	=> '0401-042.php',
		'old'	=> '0.4.0.1'
	),
	'0.4.2.1' => array(
		'file'	=> '042-0421.php',
		'old'	=> '0.4.2'
	),
	'0.4.3'	=> array(
		'file'	=> '0421-043.php',
		'old'	=> '0.4.2.1'
	),
	'0.4.3.1' => array(
		'file'	=> '043-0431.php',
		'old'	=> '0.4.3'
	),
	'0.4.4' => array(
		'file'	=> '0431-044.php',
		'old'	=> '0.4.3.1'
	),
	'0.4.5' => array(
		'file'	=> '044-045.php',
		'old'	=> '0.4.4'
	),
	'0.4.5.1' => array(
		'file'	=> '045-0451.php',
		'old'	=> '0.4.5'
	),
	'0.4.6' => array(
		'file'	=> '0451-046.php',
		'old'	=> '0.4.6'
	)
);
?>