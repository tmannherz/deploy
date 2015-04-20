<?php
/**
 * Deploy Manager
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
     * @var Deployer
     */
    protected $deployer;

    /**
     * Allowed custom project type defaults.
     *
     * @var string
     */
    protected $allowedTypes = [
        'zend' => 'Deploy\Project\ZendFramework',
        'magento' => 'Deploy\Project\Magento'
    ];

    /**
     * @param string $projectPath
     * @param string $configDir
     * @param string $branch
     * @param string|bool $perms
     */
    public function __construct ($projectPath, $configDir, $branch = '', $perms = false)
    {
        $this->path = $projectPath;
        $this->name = pathinfo($projectPath, PATHINFO_BASENAME);

        $type = $environment = $defaultBranch = $hooks = $useComposer = null;
        $projectFile = $this->path . '/deploy.xml';
        if (file_exists($projectFile)) {
            $xml = @simplexml_load_file($projectFile);
            if (!$xml) {
                throw new Exception('Unable to parse project deploy.xml file.');
            }
            $type = isset($xml->project->type) ? (string)$xml->project->type : false;
            $defaultBranch = isset($xml->project->default_branch) ? (string)$xml->project->default_branch : false;
            $environment = isset($xml->project->environment) ? (string)$xml->project->environment : false;
            $useComposer = isset($xml->project->use_composer) ? true : false;
            $hooks = isset($xml->project->hooks) ? $xml->project->hooks : null;
        }
        if ($type && array_key_exists($type, $this->allowedTypes)) {
            $projectClass = $this->allowedTypes[$type];
            $project = new $projectClass($environment, $hooks, $useComposer);
        }
        else {
            $project = new Project($environment, $hooks, $useComposer);
        }
        if (!$branch && $defaultBranch) {
            $branch = $defaultBranch;
        }

        $this->deployer = new Deployer($this->path, $project, $branch, $perms);
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