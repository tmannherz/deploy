<?php

/**
 * Deployment Lib Classes
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
namespace Deploy;
use Exception;

/**
 * Deployment management model
 */
class Manager
{
    /**
     * File path to the project dir.
     * 
     * @var string
     */
    protected $path;
    
    /**
     * Name of the project dir.
     * 
     * @var string
     */
    protected $name;
    
    /**
     * @var Deployer
     */
    protected $deployer;

    /**
     * Allowed custom project type defaults.
     *
     * @var string
     */
    protected $allowedTypes = [
        'zend' => 'Deploy\ZendFrameworkProject',
        'magento' => 'Deploy\MagentoProject'
    ];
    
    /**
     * @param string $projectPath
     * @param string $configDir
     * @param string $branch
     */
    public function __construct ($projectPath, $configDir, $branch = '')
    {
        $this->path = $projectPath;
        $this->name = pathinfo($projectPath, PATHINFO_BASENAME);
        
        $customProj = null;
        $customClass = ucfirst(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $this->name)));  // MyApp.com -> Myappcom
        $customFile = $configDir . DIRECTORY_SEPARATOR . $customClass . '.php';
        if (file_exists($customFile)) {
            include $customFile;
            if (class_exists($customClass)) {
                $customProj = new $customClass();
                if (!($customProj instanceof Project)) {
                    $customProj = null;
                }
            }
            else if (defined('DEPLOY_PROJECT_TYPE') && array_key_exists(DEPLOY_PROJECT_TYPE, $this->allowedTypes)) {
                $customClass = $this->allowedTypes[DEPLOY_PROJECT_TYPE];
                $customProj = new $customClass();
            }
        }
        if (!$branch && defined('DEPLOY_BRANCH')) {
            $branch = DEPLOY_BRANCH;
        }
        $env = null;
        if (defined('DEPLOY_PROJECT_ENV')) {
            $env = DEPLOY_PROJECT_ENV;
        }

        $this->deployer = new Deployer($this->path, $customProj, $branch, $env);
    }

    /**
     * Deploy the project.
     *
     * @return bool|string
     */
    public function deployProject ()
    {
        try {
            return $this->deployer->deploy();
        } catch (Exception $e) {
            return $e->getMessage() . "\n" . $e->getTraceAsString();
        }
    }

    /**
     * @return array
     */
    public function getSteps ()
    {
        return $this->deployer->getSteps();
    }
}

/**
 * Deployment model
 */
class Deployer
{
    const BUILD_DIR = 'builds';
    const REPO_DIR = 'repo';
    const SHARED_DIR = 'shared';
    const CURRENT_DIR = 'current';
    
    /**
     * Custom Project model
     *
     * @var Project
     */
    protected $project;

    /**
     * File path to the project dir.
     *
     * @var string
     */
    protected $path;

    /**
     * Build path
     *
     * @var string
     */
    protected $build;

    /**
     * Repo path
     *
     * @var string
     */
    protected $repo;

    /**
     * Shared path
     *
     * @var string
     */
    protected $shared;

    /**
     * Current sym link
     *
     * @var string
     */
    protected $current;
    
    /**
     * Deployment steps
     *
     * @var array
     */
    protected $steps = [];

    /**
     * Git branch
     *
     * @var string
     */
    protected $branch = 'master';

    /**
     * Environment
     *
     * @var string
     */
    public $env = null;

    /**
     * @param string $projectPath
     * @param \Deploy\Project $project
     * @param string $branch
     * @param string $env
     */
    public function __construct ($projectPath, Project $project = null, $branch = null, $env = null)
    {
        $ds = DIRECTORY_SEPARATOR;
        $this->path = $projectPath;
        $this->repo = $projectPath . $ds . self::REPO_DIR;
        $this->current = $projectPath . $ds . self::CURRENT_DIR;
        $this->build = $projectPath . $ds . self::BUILD_DIR;
        $this->shared = $projectPath . $ds . self::SHARED_DIR;
        if ($branch) {
            $this->branch = $branch;
        }
        $this->env = $env;
        $this->project = $project;
    }
    /**
     * Deploy the project.
     * 
     * @return bool
     */
    public function deploy ()
    {
        $this->build .= DIRECTORY_SEPARATOR . date('YmdHis');
        
        $this->steps = $commands = [];
        chdir($this->path);

        // create new build dir
        $this->steps[] = 'Creating build directory...';
        $commands[] = sprintf('mkdir -m 777 %s', $this->build);

        $gitCmd = sprintf(
            'git --git-dir="%1$s/.git" --work-tree="%1$s" ',
            $this->repo
        );

        // check that we're on the same branch
        $currentBranch = trim(exec($gitCmd . 'rev-parse --abbrev-ref HEAD'));
        if ($currentBranch && $currentBranch != 'HEAD' && $currentBranch != $this->branch) {
            $this->steps[] = 'Fetching branches...';
            $commands[] = sprintf(
                '%1$s fetch',
                $gitCmd
            );

            $this->steps[] = 'Switching branch...';
            $commands[] = sprintf(
                '%1$s checkout %2$s',
                $gitCmd,
                $this->branch
            );
        }
        else {
            // reset the repo to the HEAD
            $this->steps[] = 'Resetting repository...';
            $commands[] = sprintf(
                '%1$s reset --hard FETCH_HEAD',
                $gitCmd
            );                       
        }

        // update repo
        $this->steps[] = 'Updating repository...';
        $commands[] = sprintf(
            '%1$s pull',
            $gitCmd
        ); 

        // export the repo
        $this->steps[] = 'Exporting repository...';
        $commands[] = sprintf(
            '%1$s archive %2$s | tar -x -C %3$s',
            $gitCmd,
            $this->branch,
            $this->build
        );

        $output = [];      
        foreach ($commands as $index => $command) {
            exec($command, $output, $response);
            if ($response !== 0) {
                $this->steps[$index] .= 'Error';
                throw new Exception("Error executing command $index.");
            }
            $this->steps[$index] .= 'Done';
        }

        // project-specific pre
        if ($this->project) {
            $step = 'Running project pre-deployment commands...';
            try {
                if ($this->project->beforeDeploy($this)) {
                    $this->steps[] = $step . 'Done';
                }
                else {
                    $this->steps[] = $step . 'Error';
                }
            } catch (Exception $e) {
                $this->steps[] = $step . 'Error';
                throw $e;
            }
        }

        // point current to the build
        @unlink($this->current);
        if (!@symlink($this->build, $this->current)) {
            throw new Exception('Error linking to the current directory.');
        }

        // project-specific post
        if ($this->project) {
            $step = 'Running project post-deployment commands...';
            try {
                if ($this->project->afterDeploy($this)) {
                    $this->steps[] = $step . 'Done';
                }
                else {
                    $this->steps[] = $step . 'Error';
                }
            } catch (Exception $e) {
                $this->steps[] = $step . 'Error';
                throw $e;
            }
        }

        return true;
    }
    
    /**
     * @return array
     */
    public function getSteps ()
    {
        return $this->steps;
    }

    /**
     * @return string
     */
    public function getBuildPath ()
    {
        return $this->build;
    }

    /**
     * @return string
     */
    public function getRepoPath ()
    {
        return $this->repo;
    }

    /**
     * @return string
     */
    public function getSharedPath ()
    {
        return $this->shared;
    }
}

/**
 * Custom Project class
 */
abstract class Project
{
    /**
     * Run custom tasks before the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function beforeDeploy (Deployer $deployer)
    {
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
        return true;
    }

    /**
     * Clear app cache.
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
}

/**
 * Zend Framework Project
 */
class ZendFrameworkProject extends Project
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
        return true;
    }
}

/**
 * Magento Project
 */
class MagentoProject extends Project
{
    /**
     * Run custom tasks before the project is deployed.
     *
     * @param Deployer $deployer
     * @return bool
     */
    public function beforeDeploy (Deployer $deployer)
    {
        if (file_exists($deployer->getBuildPath() . DIRECTORY_SEPARATOR . 'maintenance.flag.disabled')) {
            @rename($deployer->getBuildPath() . DIRECTORY_SEPARATOR . 'maintenance.flag.disabled', $deployer->getBuildPath() . DIRECTORY_SEPARATOR . 'maintenance.flag');
        }

        $res = @symlink($deployer->getSharedPath() . '/media', $deployer->getBuildPath() . '/media');
        if ($res) {
            $res = @symlink($deployer->getSharedPath() . '/var', $deployer->getBuildPath() . '/var');
        }

        // Environment-specific files
        if ($deployer->env) {
            $files = [
                '/.htaccess',
                '/errors/local.xml',
                '/app/etc/local.xml',
                '/newrelic.php',
                '/robots.txt'
            ];
            foreach ($files as $file) {
                if ($res && file_exists($deployer->getBuildPath() . $file . '.' . $deployer->env)) {
                    $res = @rename($deployer->getBuildPath() . $file . '.' . $deployer->env, $deployer->getBuildPath() . $file);
                }
            }
        }

        // Sym-link if env-specific config doesn't exist.
        if ($res && !file_exists($deployer->getBuildPath() . '/app/etc/local.xml') && file_exists($deployer->getSharedPath() . '/app/etc/local.xml')) {
            $res = @symlink($deployer->getSharedPath() . '/app/etc/local.xml', $deployer->getBuildPath() . '/app/etc/local.xml');
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

        if (file_exists($deployer->getBuildPath() . DIRECTORY_SEPARATOR . 'maintenance.flag')) {
            @rename($deployer->getBuildPath() . DIRECTORY_SEPARATOR . 'maintenance.flag', $deployer->getBuildPath() . DIRECTORY_SEPARATOR . 'maintenance.flag.disabled');
        }

        return true;
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

        require $deployer->getBuildPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';

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