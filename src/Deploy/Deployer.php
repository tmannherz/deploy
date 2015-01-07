<?php
/**
 * Deployer model
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */
 
namespace Deploy;

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
     * File permissions.
     *
     * @var string
     */
    protected $perms = null;

    /**
     * @param string $projectPath
     * @param \Deploy\Project $project
     * @param string $branch
     * @param string $perms
     */
    public function __construct ($projectPath, Project $project, $branch = null, $perms = null)
    {
        $ds = '/';  //DIRECTORY_SEPARATOR;
        $this->path = $projectPath;
        $this->repo = $projectPath . $ds . self::REPO_DIR;
        $this->current = $projectPath . $ds . self::CURRENT_DIR;
        $this->build = $projectPath . $ds . self::BUILD_DIR;
        $this->shared = $projectPath . $ds . self::SHARED_DIR;

        $this->project = $project;
        if ($branch) {
            $this->branch = $branch;
        }
        $this->perms = $perms;
    }
    /**
     * Deploy the project.
     *
     * @return bool
     */
    public function deploy ()
    {
        $os = php_uname('s');
        /**
         * Windows only supported via Git Bash
         */
        $isWin = stripos($os, 'windows') !== false ? true : false;

        $this->build .= '/' . date('YmdHis');

        $this->steps = $commands = [];
        chdir($this->path);

        // create new build dir
        $this->steps[] = 'Creating build directory...';
        if ($isWin) {
            $commands[] = sprintf('mkdir "%s"', $this->build);
        }
        else {
            $commands[] = sprintf('mkdir -m 777 %s', $this->build);
        }

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
                '%1$s fetch && %1$s reset --hard FETCH_HEAD',
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

        if (!$isWin && $this->perms) {
            // update file permissions
            $this->steps[] = 'Updating file permissions...';
            $commands[] = sprintf(
                'chmod -R %s %s',
                $this->perms,
                $this->build
            );
        }

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

        // point current to the build
        @unlink($this->current);
        if (!@symlink($this->build, $this->current)) {
            throw new Exception('Error linking to the current directory.');
        }

        // project-specific post
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
     * @param string $step
     * @return array
     */
    public function addStep ($step)
    {
        return $this->steps[] = $step;
    }

    /**
     * @return string
     */
    public function getProjectPath ()
    {
        return $this->path;
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
