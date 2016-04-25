<?php
/**
 * Deployer utility functions
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */

/**
 * Recursively remove a non-empty directory.
 *
 * @param string $dir
 */
function rrmdir ($dir) { 
    if (is_dir($dir)) { 
        $objects = scandir($dir); 
        foreach ($objects as $object) { 
            if ($object != '.' && $object != '..') { 
                if (filetype($dir . '/' . $object) == 'dir') {
                    rrmdir($dir . '/' . $object); 
                }
                else {
                    unlink($dir . '/' . $object); 
                }
            } 
        } 
        reset($objects); 
        rmdir($dir); 
    } 
}

/**
 * Parse and return an arg from the CLI input.
 *
 * @param string $requestedArg
 * @return string|false
 */
function getCliArg ($requestedArg)
{
    static $args;
    if (!is_array($args)) {
        $args = [];
        $current = null;
        foreach ($_SERVER['argv'] as $arg) {
            $match = array();
            if (preg_match('#^--([\w\d_-]{1,})$#', $arg, $match) || preg_match('#^-([\w\d_]{1,})$#', $arg, $match)) {
                $current = $match[1];
                $args[$current] = true;
            }
            else {
                if ($current) {
                    $args[$current] = $arg;
                }
                else if (preg_match('#^([\w\d_]{1,})$#', $arg, $match)) {
                    $args[$match[1]] = true;
                }
            }
        }
    }
    return $args[$requestedArg] ?? false;
}
