# Deploy

PHP script for the deployment of Git projects.

## Setup

* Edit `{repo_root}/example/config.xml` and save as `{repo_root}/config.xml`.
* Edit `{repo_root}/example/deploy.xml` and save it in the project directory as `deploy.xml`.
* Edit `{repo}/.git/config` and change the URL to use SSH: 
```
 -url = https://bitbucket.org/{user}/{repo}.git
 +url = git@bitbucket.org:{user}/{repo}.git
```
* Generate SSH keys:
```
$ ssh-keygen -t rsa -C "{label}"
$ eval `ssh-agent -s`
$ ssh-add
```
* Add public key to BitBucket and run `ssh-add ~/.ssh/{key}_id_rsa` to register the private key.
* Setup server private key to automatically authenticate with BitBucket. In `~/.ssh/config`, add:

```
Host bb
        Hostname bitbucket.org
        User {user}
        IdentityFile ~/.ssh/{key}_id_rsa
```


## Deployment & Hooks

### deploy.xml
```
#!xml
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

### afterDeploy.php
```
#!php
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
