<?php

use Deploy\Project;

/**
 * Striker Deploy class
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
class Strikerappcom implements \Deploy\CustomProject
{
    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Project $project
     * @return bool
     */
    public function afterDeploy (Project $project)
    {
        $res = true;
        if (is_dir($project->getBuildPath() . '/tmp')) {
            $res = @rmdir($project->getBuildPath() . '/tmp');
        }
        if ($res) {
            $res = @symlink($project->getSharedPath() . '/tmp', $project->getBuildPath() . '/tmp');
        }
        return $res;
    }
}