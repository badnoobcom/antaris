<?php
/**
 *          A    N    T    A    R    I    S
 * ====================================================
 * ======= an apache mod_jk <--> php connector ========
 * ====================================================
 *
 * @author Daniel Bunte <daniel.bunte@badnoob.com>
 */

declare(strict_types=1);

$strBootstrapInclude = 'com\badnoob\antaris\core\bootstrap\Bootstrapper.php';
$strBootstrapFilepath = str_replace('\\', DIRECTORY_SEPARATOR, $strBootstrapInclude);
echo 'Loading Bootstrapper: "'. $strBootstrapFilepath .'"'. PHP_EOL;

include_once $strBootstrapFilepath;
use com\badnoob\antaris\core\bootstrap\Bootstrapper;
$objBootstrapper = new Bootstrapper();

use com\badnoob\antaris\core\AntarisCore;
$objAntarisCore = new AntarisCore();
