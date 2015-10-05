<?php
/**
 * Git project deployment script.
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */

namespace Deploy;

echo "\n";
echo "#############################\n";
echo "##                         ##\n";
echo "##  GIT DEPLOYMENT SCRIPT  ##\n";
echo "##                         ##\n";
echo "#############################\n\n";

$xmlFile = dirname(__FILE__) . '/config.xml';
if (!file_exists($xmlFile)) {
    echo "Config XML not found. Please see config.xml.sample.\n";
    exit;
}
$xml = @simplexml_load_file($xmlFile);
if (!$xml) {
    echo "Unable to read config.xml.\n";
    exit;
}

$wwwDir = (string)$xml->deploy->directory;
if (!$wwwDir) {
    echo "Project base directory not defined. Please see config.xml.sample.\n";
    exit;
}
$perms = isset($xml->deploy->permissions) ? (string)$xml->deploy->permissions : false;

$projects = [];
$iterator = new \DirectoryIterator($wwwDir);
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isDir()) {
        if (is_dir($fileInfo->getRealPath() . '/builds') && is_dir($fileInfo->getRealPath() . '/repo')) {
           $projects[] = $fileInfo->getFilename();
        }
    }
}
if (count($projects) == 1) {
    $project = $projects[0];
}
else if (count($projects)) {
    sort($projects);
    foreach ($projects as $index => $project) {
        echo ($index + 1) . ') ' . $project . "\n";
    }
    echo "\n";
    do {
        echo "Select the project to deploy: ";
        $choice = (int)trim(fgets(STDIN));
    } while (!isset($projects[$choice - 1]));
    $project = $projects[$choice - 1];
    echo "\n";
}
else {
    echo "No projects suitable for deployment.\n";
    exit;
}

echo 'Specify branch to deploy ' . $project . ' (leave blank to use default): ';
$branch = trim(fgets(STDIN));

echo "\nAbout to deploy " . ($branch ? $branch : 'default') . ' branch for ' . $project . ".\nDo you wish to continue? (y/n): ";
$continue = strtolower(trim(fgets(STDIN)));
if ($continue != 'y' && $continue != 'yes') {
    echo "Aborting...\n";
    exit;
}

/**
 * Setup the autoloader.
 */
use Deploy\Autoloader;
require_once __DIR__ . '/src/Deploy/Autoloader.php';
require_once __DIR__ . '/src/util.php';
$autoloader = new Autoloader(__NAMESPACE__, __DIR__ . DIRECTORY_SEPARATOR . 'src');
$autoloader->register();

try {
    $manager = new Manager(
        $wwwDir . '/' . $project,
        $wwwDir . '/deploy/projects',
        $branch,
        $perms
    );
    $result = $manager->deployProject();
    $steps = $manager->getSteps();
    echo implode("\n", $steps);
    if (is_string($result)) {
        echo "\nDeployment failed.\n";
        echo $result;
    }
    else if ($result === true) {
        echo "\nProject successfully deployed.";
    }
    else {
        echo "\nDeployment failed.";
    }
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    echo "\nDeployment failed.";
}

echo "\n";
