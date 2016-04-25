<?php
/**
 * Magento Project
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */

namespace Deploy\Project;
use Deploy\Project;
use Deploy\Deployer;

class Magento extends Project
{
    /**
     * Magento constructor.
     * 
     * @param string $env
     * @param \SimpleXMLElement $hooks
     * @param bool $useComposer
     * @param array $perms
     */
    public function __construct ($env, \SimpleXMLElement $hooks, $useComposer, array $perms)
    {
        if (!isset($perms['directory'])) {
            $perms['directory'] = '550';
        }
        if (!isset($perms['file'])) {
            $perms['file'] = '440';
        }
        parent::__construct($env, $hooks, $useComposer, $perms);
    }

    /**
     * Run custom tasks before the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function beforeDeploy (Deployer $deployer)
    {
        if (file_exists($deployer->getBuildPath() . '/maintenance.flag.disabled')) {
            @rename($deployer->getBuildPath() . '/maintenance.flag.disabled', $deployer->getBuildPath() . '/maintenance.flag');
        }

        $res = @symlink($deployer->getSharedPath() . '/media', $deployer->getBuildPath() . '/media');
        if ($res) {
            $res = @symlink($deployer->getSharedPath() . '/var', $deployer->getBuildPath() . '/var');
        }

        // Environment-specific files
        if ($this->env) {
            $files = [
                '/.htaccess',
                '/errors/local.xml',
                '/app/etc/local.xml',
                '/newrelic.php',
                '/robots.txt'
            ];
            foreach ($files as $file) {
                if ($res && file_exists($deployer->getBuildPath() . $file . '.' . $this->env)) {
                    $res = @rename($deployer->getBuildPath() . $file . '.' . $this->env, $deployer->getBuildPath() . $file);
                }
            }
        }

        // Sym-link if env-specific config doesn't exist.
        if ($res && !file_exists($deployer->getBuildPath() . '/app/etc/local.xml') && file_exists($deployer->getSharedPath() . '/app/etc/local.xml')) {
            $res = @symlink($deployer->getSharedPath() . '/app/etc/local.xml', $deployer->getBuildPath() . '/app/etc/local.xml');
        }

        // remove dev directory
        if ($res && file_exists($deployer->getBuildPath() . '/dev')) {
            @rrmdir($deployer->getBuildPath() . '/dev');
        }

        if ($res) {
            return parent::beforeDeploy($deployer);
        }
        return false;
    }

    /**
     * Update file permissions for the deployed project.
     *
     * @link http://devdocs.magento.com/guides/m1x/install/installer-privileges_after.html
     * @param Deployer $deployer
     * @return bool
     */
    public function setFilePermissions (Deployer $deployer)
    {
        $res = parent::setFilePermissions($deployer);
        if ($res) {
            $res = $this->exec('chmod +x ' . $deployer->getBuildPath() . '/cron.sh');
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

        if (file_exists($deployer->getBuildPath() . '/maintenance.flag')) {
            @rename($deployer->getBuildPath() . '/maintenance.flag', $deployer->getBuildPath() . '/maintenance.flag.disabled');
        }

        return parent::afterDeploy($deployer);
    }

    /**
     * Clear app cache.
     *
     * @param Deployer $deployer
     * @return bool
     */
    protected function clearCache (Deployer $deployer)
    {
        parent::clearCache($deployer);

        require $deployer->getBuildPath() . '/app/Mage.php';

        if (!\Mage::isInstalled()) {
            return true;
        }
        // Only for urls
        // Don't remove this
        $_SERVER['SCRIPT_NAME'] = '/';
        $_SERVER['SCRIPT_FILENAME'] = '/';

        \Mage::app('admin')->setUseSessionInUrl(false);

        umask(0);

        \Mage::app()->getCacheInstance()->flush();

        /**
         * Run db updates
         */
        \Mage::getConfig()->reinit();
        \Mage_Core_Model_Resource_Setup::applyAllUpdates();
        \Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

        return true;
    }
}