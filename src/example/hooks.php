<?php

use Deploy\Deployer;

function afterDeploy (Deployer $deployer)
{
    /**
     * Clear opcode cache.
     */
    $script = 'https://dev.safedinar.com/externals/clear-cache.php?type=opcache';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $script);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERPWD, 'admin:dartmouth');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    /**
     * New Relic deployment alert.
     *
     * @link https://docs.newrelic.com/docs/agents/php-agent/features/recording-deployments-using-php-script
     */
    $url = "https://api.newrelic.com/deployments.xml";
    $apiKey = '1ed1a324c12415a2435415cd0ef1e895391ecec7e70907d';
    $header = ['x-api-key:' . $apiKey];
    $appName = 'Magento - Dev';
    $depDescription = $deployer->getBuildDate() . ' deployment.';
    $depData = 'deployment[app_name]=' . $appName;
    $depData .= '&deployment[description]=' . $depDescription;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $depData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    return true;
}
