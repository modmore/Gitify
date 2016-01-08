<?php

/**
 * Make sure dependencies have been installed, and load the autoloader.
 */
$file = $file = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($file)) {
    require $file;
} else if (!class_exists(modmore\Gitify\Gitify, false)) {
    throw new \Exception('Uh oh, it looks like dependencies have not yet been installed with Composer. Please follow the installation instructions at https://github.com/modmore/Gitify/wiki/1.-Installation');
}

/**
 * Ensure the timezone is set; otherwise you'll get a shit ton (that's a technical term) of errors.
 */
if (version_compare(phpversion(),'5.3.0') >= 0) {
    $tz = @ini_get('date.timezone');
    if (empty($tz)) {
        date_default_timezone_set(@date_default_timezone_get());
    }
}

/**
 * Specify the working directory, if it hasn't been set yet.
 */
if (!defined('GITIFY_WORKING_DIR')) {
    $cwd = getcwd() . DIRECTORY_SEPARATOR;
    $cwd = str_replace('\\', '/', $cwd);
    define ('GITIFY_WORKING_DIR', $cwd);
}

/**
 * Specify the user home directory, for save cache folder of gitify
 */
if (!defined('GITIFY_CACHE_DIR')) {
    $home = rtrim(getenv('HOME'), DIRECTORY_SEPARATOR);
    if (!$home && isset($_SERVER['HOME'])) {
        $home = rtrim($_SERVER['HOME'], DIRECTORY_SEPARATOR);
    }
    if (!$home && isset($_SERVER['HOMEDRIVE']) && isset($_SERVER['HOMEPATH'])) {
        // compatibility to Windows
        $home = rtrim($_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'], DIRECTORY_SEPARATOR);
    }
    if (!$home) {
        // fallback to working directory, if home directory can not be determined
        $home = rtrim(GITIFY_WORKING_DIR, DIRECTORY_SEPARATOR);
    }
    define('GITIFY_CACHE_DIR', implode(DIRECTORY_SEPARATOR, array($home, '.gitify', '')));
}

/**
 * Load all the commands and create the Gitify instance
 */
use modmore\Gitify\Command\Init;
use modmore\Gitify\Command\Build\Build;
use modmore\Gitify\Command\Build\Extract;
use modmore\Gitify\Command\Backup\Backup;
use modmore\Gitify\Command\Backup\Restore;
use modmore\Gitify\Command\ClearCache;
use modmore\Gitify\Command\Modx\Install as ModxInstall;
use modmore\Gitify\Command\Modx\Upgrade as ModxUpgrade;
use modmore\Gitify\Command\Package\Install as PackageInstall;
use modmore\Gitify\Command\Package\Dump as PackageDump;
use modmore\Gitify\Gitify;

$composerData = file_get_contents(__DIR__ . "/composer.json");
$composerData = json_decode($composerData, true);
$version = $composerData['version'];

$application = new Gitify('Gitify', $version);
$application->add(new Init);
$application->add(new Build);
$application->add(new Extract);
$application->add(new ClearCache);
$application->add(new Backup);
$application->add(new Restore);
$application->add(new ModxInstall());
$application->add(new ModxUpgrade());
$application->add(new PackageInstall);
$application->add(new PackageDump());

/**
 * We return it so the CLI controller in /Gitify can run it, or for other integrations to
 * work with the Gitify api directly.
 */
return $application;
