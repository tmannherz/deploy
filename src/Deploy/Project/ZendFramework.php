<?php
/**
 * Zend Framework Project
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
 
namespace Deploy\Project;
use Deploy\Project;
use Deploy\Deployer;

class ZendFramework extends Project
{
    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function beforeDeploy (Deployer $deployer)
    {
        $res = true;
        if (is_dir($deployer->getBuildPath() . '/tmp')) {
            $res = @rmdir($deployer->getBuildPath() . '/tmp');
        }
        if ($res) {
            $res = @symlink($deployer->getSharedPath() . '/tmp', $deployer->getBuildPath() . '/tmp');
        }
        if ($res) {
            $res = parent::beforeDeploy($deployer);
        }
        return $res;
    }

    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function afterDeploy (Deployer $deployer)
    {
        $this->clearCache($deployer);
        return parent::afterDeploy($deployer);
    }
}