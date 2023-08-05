<?php

if (!function_exists('vite')) {
    /**
     * Get a route by name
     * @param string|array $route The route to get
     * @param string $baseDir The base directory to look for the file(s)
     */
    function vite($files, $baseDir = 'app/views')
    {
        if (is_array($files)) {
            $files = array_map(function ($file) use ($baseDir) {
                if (strpos($file, $baseDir) !== false) {
                    return $file;
                }

                return trim($baseDir, '/') . '/' . ltrim($file, '/');
            }, $files);
        } else if (is_string($files)) {
            if (strpos($files, $baseDir) === false) {
                $files = trim($baseDir, '/') . '/' . ltrim($files, '/');
            }

            $files = [$files];
        }

        return \Leaf\Vite::build($files);
    }
}
