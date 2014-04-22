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
     * @var Project
     */
    protected $project;

    /**
     * Allowed custom project type defaults.
     *
     * @var string
     */
    protected $allowedTypes = array(
        'zend' => 'Deploy\ZendFrameworkProject',
        'magento' => 'Deploy\MagentoProject'
    );
    
    /**
     * @param string $projectPath
     * @param string $configDir
     */
    public function __construct ($projectPath, $configDir)
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
                if (!($customProj instanceof CustomProject)) {
                    $customProj = null;
                }
            }
            else if (defined('DEPLOY_PROJECT_TYPE') && array_key_exists(DEPLOY_PROJECT_TYPE, $this->allowedTypes)) {
                $customClass = $this->allowedTypes[DEPLOY_PROJECT_TYPE];
                $customProj = new $customClass();
            }
        }
        $branch = null;
        if (defined('DEPLOY_BRANCH')) {
            $branch = DEPLOY_BRANCH;
        }

        $this->project = new Project($this->path, $customProj, $branch);
    }

    /**
     * Deploy the project.
     *
     * @return bool|string
     */
    public function deployProject ()
    {
        try {
            return $this->project->deploy();
        } catch (Exception $e) {
            return $e->getMessage() . "\n" . $e->getTraceAsString();
        }
    }

    /**
     * @return array
     */
    public function getSteps ()
    {
        return $this->project->getSteps();
    }
}

/**
 * Project model
 */
class Project
{
    const BUILD_DIR = 'builds';
    const REPO_DIR = 'repo';
    const SHARED_DIR = 'shared';
    const CURRENT_DIR = 'current';
    
    /**
     * Custom Project model
     *
     * @var CustomProject
     */
    protected $customProject;

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
    protected $steps = array();

    /**
     * Git branch
     *
     * @var string
     */
    protected $branch = 'master';

    /**
     * @param string $projectPath
     * @param \Deploy\CustomProject $customProject
     * @param string $branch
     */
    public function __construct ($projectPath, CustomProject $customProject = null, $branch = null)
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
        $this->customProject = $customProject;
    }
    /**
     * Deploy the project.
     * 
     * @return bool
     */
    public function deploy ()
    {
        $this->build .= DIRECTORY_SEPARATOR . date('YmdHis');
        
        $this->steps = $commands = array();
        chdir($this->path);

        // create new build dir
        $this->steps[] = 'Creating build directory...';
        $commands[] = sprintf('mkdir -m 777 %s', $this->build);

        // reset the repo to the HEAD
        $this->steps[] = 'Resetting repository...';
        $commands[] = sprintf(
            'git --git-dir="%1$s/.git" --work-tree="%1$s" reset --hard FETCH_HEAD',
            $this->repo
        );

        // update repo
        $this->steps[] = 'Updating repository...';
        $commands[] = sprintf(
            'git --git-dir="%1$s/.git" --work-tree="%1$s" pull',
            $this->repo
        );

        // export the repo
        $this->steps[] = 'Exporting repository...';
        $commands[] = sprintf(
            'git --git-dir="%1$s/.git" --work-tree="%1$s" archive %2$s | tar -x -C %3$s',
            $this->repo,
            $this->branch,
            $this->build
        );

        $output = array();      
        foreach ($commands as $index => $command) {
            exec($command, $output, $response);
            if ($response !== 0) {
                $this->steps[$index] .= 'Error';
                throw new Exception("Error executing command $index.");
            }
            $this->steps[$index] .= 'Done';
        }

        // project-specific
        if ($this->customProject) {
            $step = 'Running Project-specific commands...';
            try {
                if ($this->customProject->afterDeploy($this)) {
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
abstract class CustomProject
{
    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Project $project
     * @return bool
     */
    public abstract function afterDeploy (Project $project);

    /**
     * Clear app cache.
     *
     * @param Project $project
     * @return bool
     */
    protected function clearCache (Project $project)
    {
        try {
            if (extension_loaded('apc') && ini_get('apc.enabled')) {
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
class ZendFrameworkProject extends CustomProject
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
        if ($res) {
            return $this->clearCache($project);
        }
        return $res;
    }   
}

/**
 * Magento Project
 */
class MagentoProject extends CustomProject
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
        if ($res) {
            return $this->clearCache($project);
        }
        return $res;
    }
    
    /**
     * Clear app cache.
     * 
     * @param Project $project
     * @return bool
     */
    protected function clearCache (Project $project)
    {
        require $project->getBuildPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';

        if (!\Mage::isInstalled()) {
            return true;
        }
        // Only for urls
        // Don't remove this
        $_SERVER['SCRIPT_NAME'] = '/';
        $_SERVER['SCRIPT_FILENAME'] = '/';

        \Mage::app('admin')->setUseSessionInUrl(false);

        umask(0);

        \Mage::app()->cleanCache();
        \Mage::app()->getCache()->getBackend()->clean();
        if (class_exists('\\Enterprise_PageCache_Model_Cache')) {
            \Enterprise_PageCache_Model_Cache::getCacheInstance()->getFrontend()->getBackend()->clean();
        }

        parent::clearCache($project);

        /**
         * Run db updates
         */
        \Mage::getConfig()->reinit();
        \Mage_Core_Model_Resource_Setup::applyAllUpdates();
        \Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

        return true;
    }
}