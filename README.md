# Deploy

PHP script for the deployment of Git projects.

## Directory & Repository Setup
Required directory setup:
```
www_dir
|
--- deploy  # this repo
|   |-- config.xml
|   |-- ...
|
--- my.project.com
    |-- builds
    |-- current  # symlink to current build directory
    |-- repo  # git clone of project being deployed
    |-- shared  # shared resources for each build (logs, sessions, var, media, etc)
    |-- deploy.xml
    |-- hooks.php
--- my.second.com
    |-- ...
```

## SSH Setup

* Edit `{repo_root}/example/config.xml` and save as `{repo_root}/config.xml`.
* Edit `{repo_root}/example/deploy.xml` and save it in the project directory as `deploy.xml`.
* Edit `{repo}/.git/config` and change the URL to use SSH: 

    ```
     -url = https://github.com/{user}/{repo}.git
     +url = git@github.com:{user}/{repo}.git
    ```
* Generate SSH keys:

    ```shell
    ssh-keygen -t rsa -C "{label}"
    eval `ssh-agent -s`
    ssh-add
    ```
* Add public key to GitHub and run `ssh-add ~/.ssh/{key}_id_rsa` to register the private key.
* Setup server private key to automatically authenticate with GitHub. In `~/.ssh/config`, add:

    ```
    Host bb
            Hostname github.com
            User {user}
            IdentityFile ~/.ssh/{key}_id_rsa
    ```

## Configuration & Hooks

##### config.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <deploy>
        <!-- Base directory to search for projects. Required. -->
        <directory>/var/www</directory>
    </deploy>
</config>
```

##### deploy.xml
```xml
<?xml version="1.0" encoding="UTF-8"?>
<config>
    <project id="my.project.com">
        <type>magento</type><!-- Optional, magento or zend -->
        <default_branch>master</default_branch>
        <environment>dev</environment>
        <use_composer /><!-- If present, run composer install after deployment -->
        <hooks><!-- Available hooks. Optional. -->
            <afterDeploy>
                <include>afterDeploy.php</include><!-- Relative to this file's path -->
                <call>afterDeploy</call><!-- Optional, function or method to invoke -->
            </afterDeploy>
        </hooks>
        <perms><!-- Default filesystem permissions -->
            <directory>775</directory>
            <file>664</file>
            <owner>www-data</owner><!-- Optional -->
        </perms>
    </project>
</config>
```

##### afterDeploy.php
```php
<?php
use Deploy\Deployer;

/**
 * @param Deployer $deployer
 * @return bool
 */
function afterDeploy (Deployer $deployer) 
{
    // do something...
    return true;
}
```


## Deployment

##### CLI input to select project and branch:

```shell
php -f deploy.php 
```

##### Deploy a pre-specified project:

```shell
php -f deploy.php -- --project "my.project.com" [--branch "dev"]
```
