<?php
/**
 * Git project deployment script.
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */

namespace Deploy;
require_once __DIR__ . '/src/util.php';

echo "\n";
echo "#############################\n";
echo "##                         ##\n";
echo "##  GIT DEPLOYMENT SCRIPT  ##\n";
echo "##                         ##\n";
echo "#############################\n\n";

$xmlFile = __DIR__ . '/config.xml';
if (!file_exists($xmlFile)) {
    echo "Config XML not found. Please see config.xml.sample.\n";
    exit(1);
}
$xml = @simplexml_load_file($xmlFile);
if (!$xml) {
    echo "Unable to read config.xml.\n";
    exit(1);
}

$wwwDir = (string)$xml->deploy->directory;
if (!$wwwDir) {
    echo "Project base directory not defined. Please see config.xml.sample.\n";
    exit(1);
}

$projects = [];
$iterator = new \DirectoryIterator($wwwDir);
foreach ($iterator as $fileInfo) {
    if ($fileInfo->isDir()) {
        if (is_dir($fileInfo->getRealPath() . '/builds') && is_dir($fileInfo->getRealPath() . '/repo')) {
           $projects[] = $fileInfo->getFilename();
        }
    }
}

$projectArg = getCliArg('project');
if ($projectArg) {
    $branch = getCliArg('branch');
    $project = false;
    foreach ($projects as $projectOpt) {
        if ($projectOpt == $projectArg) {
            $project = $projectOpt;
            break;
        }
    }
    if (!$project) {
        echo "Requested project does not exist.\n";
        exit(1);
    }
}
else {
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
        exit(1);
    }
    echo 'Specify branch to deploy ' . $project . ' (leave blank to use default): ';
    $branch = trim(fgets(STDIN));
}

echo "\nAbout to deploy " . ($branch ? $branch : 'default') . ' branch for ' . $project . ".\n";
if (!$projectArg) {
    echo 'Do you wish to continue? (y/n): ';
    $continue = strtolower(trim(fgets(STDIN)));
    if ($continue != 'y' && $continue != 'yes') {
        echo "Aborting...\n";
        exit(0);
    }
}

/**
 * Setup the autoloader.
 */
use Deploy\Autoloader;
require_once __DIR__ . '/src/Deploy/Autoloader.php';
$autoloader = new Autoloader(__NAMESPACE__, __DIR__ . DIRECTORY_SEPARATOR . 'src');
$autoloader->register();

try {
    $manager = new Manager(
        $wwwDir . '/' . $project,
        $wwwDir . '/deploy/projects',
        $branch
    );
    $result = $manager->deployProject();
    $steps = $manager->getSteps();
    echo implode("\n", $steps);
    if (is_string($result)) {
        echo "\nDeployment failed.\n";
        echo $result . "\n";
        exit(1);
    }
    else if ($result === true) {
        echo "\nProject successfully deployed.\n";
        exit(0);
    }
    else {
        echo "\nDeployment failed.\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    echo "\nDeployment failed.\n";
    exit(1);
}
