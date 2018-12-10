#!/usr/bin/php
<?php
require_once('colors.php');

/** WordPress API URL
 *
 */
define('WP_API_URL', 'http://api.wordpress.org/core/version-check/1.6/');

/**
 * Regex for WordPress version file name
 */
define('WP_VERSION_REGEX', '#/wp-includes/version.php$#');

$targetFolder = empty($argv[1]) ? '' : $argv[1];

if (empty($targetFolder)) {
    die("Usage: $argv[0] /path/to/folder\n");
}

$latestWordPressVersion = getLatestVersion();
if (empty($latestWordPressVersion)) {
    die("Failed to fetch latest version. Try again later.\n");
}

$colorss = new Colors();
echo $colorss->getColoredString("Latest WordPress version: ", "yellow");
echo $colorss->getColoredString($latestWordPressVersion, "light_green");
echo $colorss->getColoredString("\n");

$files = findVersionFiles($targetFolder);
if (empty($files)) {
    print "Failed to find any WordPress version files in $targetFolder\n";
    exit;
}

$stats = array();
$stats['OK'] = 0;
$stats['OUT'] = 0;
foreach ($files as $file) {
    $installationLocation = getInstallationLocation($file);
    $installedVersion = getInstalledVersion($file);

    if ($installedVersion == $latestWordPressVersion) {
        echo $colorss->getColoredString("OK: $installationLocation ($installedVersion)", "light_green") . "\n";
        $stats['OK']++;
    } else {
        echo $colorss->getColoredString("OUTDATED: $installationLocation ($installedVersion)", "light_red") . "\n";
        $stats['OUT']++;
    }
}
printStats($stats);
/**
 * Recursively search through a given directory to find all WordPress version files.
 *
 */
function findVersionFiles($dir)
{
    $result = array();
    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($objects as $name => $object) {
        if (preg_match(WP_VERSION_REGEX, $object->getPathname())) {
            $result[] = $object->getPathname();
        }
    }
    asort($result);
    return $result;
}

/**
 * Find WordPress installation location from version file
 *
 * This chops off /wp-includes/version.php from the end of
 * the version file location.
 */
function getInstallationLocation($file)
{
    $result = preg_replace(WP_VERSION_REGEX, '', $file);
    return $result;
}

/**
 * Fetch the information about latest version from WordPress.org
 */
function getLatestVersion()
{
    $result = '';
    $curl = curl_init(WP_API_URL);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    if (!empty($response)) {
        $response = unserialize($response);
    }
    if (!empty($response['offers'][0]['current'])) {
        $result = $response['offers'][0]['current'];
    }
    return $result;
}

/**
 * Figure out installed WordPress version from the file
 */
function getInstalledVersion($file)
{
    $result = '';
    unset($wp_version);
    require_once $file;
    if (isset($wp_version)) {
        $result = $wp_version;
    }
    return $result;
}

/**
 * Print some stats
 */
function printStats($stats)
{
    $colors = new Colors();
    $total = $stats['OK'] + $stats['OUT'];
    $health = $stats['OK'] * 100 / $total;
    echo $colors->getColoredString("Stats: checked a total of $total installations: ", "yellow", null) . "\n";
    echo $colors->getColoredString($stats['OK'] . " are OK.", "light_green") . "\n";
    echo $colors->getColoredString($stats['OUT'] . " are OUTDATED.", "light_red") . "\n";
    echo $colors->getColoredString("Health: $health%", "yellow") . "\n";
}
?>
