<?php

if (!function_exists('vite')) {
    /**
     * Get a route by name
     * @param string|array $route The route to get
     * @param string|null $baseDir The base directory to look for the file(s)
     */
    function vite($files, $baseDir = null)
    {
        $baseDir = $baseDir ?? trim(app()->config('views.path') ?? 'app/views', '/');

        if (is_array($files)) {
            $files = array_map(function ($file) use ($baseDir) {
                if (!empty($baseDir) && strpos($file, $baseDir) !== false) {
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
