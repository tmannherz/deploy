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
     * @param string $env
     * @param \SimpleXMLElement $hooks
     */
    public function __construct ($env = null, \SimpleXMLElement $hooks = null)
    {
        $this->env = $env;
        $this->hooks = $hooks;
    }

    /**
     * Run custom tasks before the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function beforeDeploy (Deployer $deployer)
    {
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
            if (extension_loaded('apc') && ini_get('apc.enabled')) {
                apc_clear_cache();
                apc_clear_cache('opcode');
                apc_clear_cache('user');
            }
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
                return call_user_func((string)$this->hooks->$hookName->call, $deployer);
            }
            return true;
        }
        return false;
    }
}
