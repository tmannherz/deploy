<?php

/**
 * Deployment Lib Classes
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
namespace Deploy;

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
     * @param string $projectPath
     * @param string $configDir
     */
    public function __construct ($projectPath, $configDir)
    {
        $this->path = $projectPath;
        $this->name = pathinfo($projectPath, PATHINFO_BASENAME);
        
        $customProj = null;
        $custom = $configDir . DIRECTORY_SEPARATOR . $this->name . '.php';
        if (file_exists($custom)) {
            include $custom;
            $className = ucfirst(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $this->name)));  // MyApp.com -> Myappcom
            if (class_exists($className)) {
                $customProj = new $className();
                if (!($customProj instanceof CustomProject)) {
                    $customProj = null;
                }
            }
        }

        $this->project = new Project($this->path, $customProj);
    }

    /**
     * Deploy the project.
     *
     * @return bool
     */
    public function deployProject ()
    {
        try {
            return $this->project->deploy();
        } catch (\Exception $e) {
            return false;
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
            'git --git-dir="%1$s/.git" --work-tree="%1$s" reset --hard',
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
                throw new \Exception("Error executing command $index.");
            }
            $this->steps[$index] .= 'Done';
        }

        // project-specific
        if ($this->customProject) {
            $step = 'Running Project-specific commands...';
            if ($this->customProject->afterDeploy($this)) {
                $this->steps[] = $step . 'Done';
            }
            else {
                $this->steps[] = $step . 'Error';
                throw new \Exception('Error running custom commands.');
            }
        }

        // point current to the build
        @unlink($this->current);
        if (!@symlink($this->build, $this->current)) {
            throw new \Exception('Error linking to the current directory.');
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
 * Custom Project interface
 */
interface CustomProject 
{
    /**
     * Run custom tasks after the project is deployed.
     *
     * @param Project $project
     * @return bool
     */
    public function afterDeploy (Project $project);
}
