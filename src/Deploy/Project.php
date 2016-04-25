<?php
/**
 * Project
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
 
namespace Deploy;

/**
 * Custom Project class
 */
class Project
{
    /**
     * Environment
     *
     * @var string
     */
    protected $env = null;

    /**
     * Hooks
     *
     * @var \SimpleXMLElement
     */
    protected $hooks = null;

    /**
     * Composer install before deployment?
     *
     * @var bool
     */
    protected $useComposer = false;

    /**
     * File permissions.
     *
     * @var array
     */
    protected $perms = [];

    /**
     * @param string $env
     * @param \SimpleXMLElement $hooks
     * @param bool $useComposer
     * @param array $perms
     */
    public function __construct ($env = null, \SimpleXMLElement $hooks = null, $useComposer = false, array $perms = [])
    {
        $this->env = $env;
        $this->hooks = $hooks;
        $this->useComposer = $useComposer;
        $this->setPerms($perms);
    }

    /**
     * Execute a system command.
     *
     * @param string $command
     * @return bool
     */
    protected function exec ($command)
    {
        $output = [];
        exec($command, $output, $res);
        return $res === 0 ? true : false;
    }

    /**
     * Update file permissions for the deployed project.
     * 
     * @param Deployer $deployer
     * @return bool
     */
    public function setFilePermissions (Deployer $deployer)
    {
        return $this->updatePathPermissions($deployer->getBuildPath(), $this->getDirectoryPerms(), $this->getFilePerms());
    }

    /**
     * Set permissions for a given path.
     *
     * @param string $path
     * @param string $directoryPerms
     * @param string $filePerms
     * @return bool
     */
    protected function updatePathPermissions ($path, $directoryPerms, $filePerms = null)
    {
        if (!$filePerms) {
            $filePerms = $directoryPerms;
        }

        $res = $this->exec(sprintf(
            'find %1$s -type d -exec chmod %2$s {} \; && find %1$s -type f -exec chmod %3$s {} \;',
            $path,
            $directoryPerms,
            $filePerms
        ));

        if ($res && $this->getPermsOwner()) {
            $res = $this->exec(sprintf(
                'chown -R %s %s',
                $this->getPermsOwner(),
                $path
            ));
        }
        return $res;
    }

    /**
     * Run custom tasks before the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function beforeDeploy (Deployer $deployer)
    {
        if ($this->useComposer) {
            $cwd = getcwd();
            $command = 'composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader';
            chdir($deployer->getBuildPath());
            $response = $this->exec($command);
            if ($cwd) {
                chdir($cwd);
            }
            if (!$response) {
                return false;
            }
        }
        $this->callHook($deployer, 'beforeDeploy');
        return true;
    }

    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function afterDeploy (Deployer $deployer)
    {
        $this->callHook($deployer, 'afterDeploy');
        return true;
    }

    /**
     * Clear app cache.
     *
     * NOTE: When run from the CLI, PHP will not clear FPM or Apache opcache.
     *
     * @param Deployer $deployer
     * @return bool
     */
    protected function clearCache (Deployer $deployer)
    {
        try {
            if (function_exists('opcache_reset') && ini_get('opcache.enable')) {
                opcache_reset();
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param Deployer $deployer
     * @param string $hookName
     * @return bool|mixed
     */
    protected function callHook (Deployer $deployer, $hookName)
    {
        if (isset($this->hooks->$hookName) && isset($this->hooks->$hookName->include)) {
            include $deployer->getProjectPath() . '/' . (string)$this->hooks->$hookName->include;
            if (isset($this->hooks->$hookName->call)) {
                if (call_user_func((string)$this->hooks->$hookName->call, $deployer)) {
                    $deployer->addStep($hookName . ' hook completed.');
                }
                else {
                    $deployer->addStep($hookName . ' hook failed.');
                }
            }
            else {
                $deployer->addStep($hookName . ' hook complete.');
            }
            return true;
        }
        return false;
    }

    /**
     * @param string|array $directory
     * @param string $file
     * @param string $owner
     * @return $this
     */
    public function setPerms ($directory, $file = null, $owner = null)
    {
        if (is_array($directory)) {
            return $this->setPerms($directory['directory'], $directory['file'] ?? null, $directory['owner'] ?? null);
        }
        $this->perms['directory'] = $directory;
        if (!$file) {
            $file = $directory;
        }
        $this->perms['file'] = $file;
        if ($owner) {
            $this->perms['owner'] = $owner;
        }
        return $this;
    }

    /**
     * @return bool|string
     */
    public function getPermsOwner ()
    {
        return $this->perms['owner'] ?? false;
    }

    /**
     * @return string
     */
    public function getDirectoryPerms ()
    {
        return $this->perms['directory'] ?? '775';
    }

    /**
     * @return string
     */
    public function getFilePerms ()
    {
        return $this->perms['file'] ?? '664';
    }
}
