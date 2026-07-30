<?php

/*
|--------------------------------------------------------------------------
| Test harness for leafs/vite
|--------------------------------------------------------------------------
| Leaf\Vite keeps all of its configuration in static properties, so every
| test resets them via reflection before running. Each test also gets a
| sandbox directory for hot files and build manifests. app() (normally
| provided by leaf core) is shimmed for the vite() helper's default
| base directory lookup.
*/

define('SANDBOX', '/tmp/vite-test-sandbox' . (getenv('TEST_TOKEN') ? '-' . getenv('TEST_TOKEN') : ''));

if (!function_exists('app')) {
    function app()
    {
        return new class () {
            public function config($key = null)
            {
                return ['views.path' => 'app/views'][$key] ?? null;
            }
        };
    }
}

function resetViteState(): void
{
    $defaults = [
        'nonce' => null,
        'integrityKey' => 'integrity',
        'entryPoints' => [],
        'paths' => [
            'hotFile' => 'hot',
            'build' => 'build',
            'assets' => '/assets',
        ],
        'manifestFilename' => 'manifest.json',
        'scriptTagAttributesResolvers' => [],
        'styleTagAttributesResolvers' => [],
        'preloadTagAttributesResolvers' => [],
        'preloadedAssets' => [],
        'manifests' => [],
    ];

    $reflection = new ReflectionClass(\Leaf\Vite::class);

    foreach ($defaults as $property => $value) {
        $reflection->setStaticPropertyValue($property, $value);
    }
}

function setupViteEnv(): void
{
    if (is_dir(SANDBOX)) {
        exec('rm -rf ' . escapeshellarg(SANDBOX));
    }

    mkdir(SANDBOX . '/build', 0777, true);

    // build and hot file paths resolve relative to the working directory
    chdir(SANDBOX);

    resetViteState();
}

/** Write a hot file so Vite runs in HMR mode */
function hotServer(string $url = 'http://localhost:5173'): void
{
    file_put_contents(SANDBOX . '/hot', $url);
}

/** Write a manifest into the sandbox build directory */
function manifest(array $manifest): void
{
    file_put_contents(SANDBOX . '/build/manifest.json', json_encode($manifest));
}

uses()->beforeEach(fn () => setupViteEnv())->in(__DIR__);
