<?php

use Deploy\Project;
use Deploy\MagentoProject;

/**
 * Define custom project type to use.
 */
define('DEPLOY_PROJECT_TYPE', 'magento');
define('DEPLOY_BRANCH', 'dev');

/**
 * Safe Dinar Deploy class
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
class Devsafedinarcom extends MagentoProject
{
    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Project $project
     * @return bool
     */
    public function afterDeploy (Project $project)
    {
        $res = parent::afterDeploy($project);
        if ($res) {
            $res = @rename($project->getBuildPath() . '/newrelic.php.dev', $project->getBuildPath() . '/newrelic.php');
        }
        if ($res) {
            $res = @rename($project->getBuildPath() . '/robots.txt.dev', $project->getBuildPath() . '/robots.txt');
        }
        return $res;
    }
}