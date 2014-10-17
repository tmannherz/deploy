<?php
/**
 * Git project deployment script.
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */

echo "\n";
echo "#############################\n";
echo "##                         ##\n";
echo "##  GIT DEPLOYMENT SCRIPT  ##\n";
echo "##                         ##\n";
echo "#############################\n\n";

$projects = [];
$wwwDir = '/var/www';
$iterator = new DirectoryIterator($wwwDir);
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

require_once 'include.php';
use Deploy\Manager;
$manager = new Manager(
    $wwwDir . DIRECTORY_SEPARATOR . $project,
    $wwwDir . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'projects',
    $branch
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
echo "\n";
