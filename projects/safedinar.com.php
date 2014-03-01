<?php

use Deploy\Project;

/**
 * Safe Dinar Deploy class
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
class Safedinarcom implements \Deploy\CustomProject
{
    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Project $project
     * @return bool
     */
    public function afterDeploy (Project $project)
    {
        $res = @symlink($project->getSharedPath() . '/app/etc/local.xml', $project->getBuildPath() . '/app/etc/local.xml');
        if ($res) {
            $res = @symlink($project->getSharedPath() . '/media', $project->getBuildPath() . '/media');           
        }
        if ($res) {
            $res = @symlink($project->getSharedPath() . '/var', $project->getBuildPath() . '/var');
        }
        return $res;
    }
}