<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

spl_autoload_register('autoLoader');


function autoLoader($strClassName)
{
	echo 'autoload request for "'. $strClassName .'"'. PHP_EOL;
	$strClassName = '..'. DIRECTORY_SEPARATOR .'src'. DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $strClassName). '.php';
	include_once $strClassName;
}