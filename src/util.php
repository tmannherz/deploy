<?php
/**
 * Deployer utility functions
 *
 * @author Todd Mannherz <todd.mannherz@gmail.com>
 */

/**
 * Recursively remove a non-empty directory.
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