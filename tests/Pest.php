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
        'hotServerResponds' => null,
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

/**
 * Write a hot file backed by a real listening socket, so isRunningHot()'s
 * dev-server probe succeeds. Returns nothing; the socket lives until the
 * process exits (or closeHotServer() is called).
 */
function hotServer(): string
{
    $GLOBALS['__hot_socket'] = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $url = 'http://' . stream_socket_get_name($GLOBALS['__hot_socket'], false);

    file_put_contents(SANDBOX . '/hot', $url);

    return $url;
}

/** Write a hot file pointing at a dev server that is NOT running */
function staleHotServer(): void
{
    // port 1 on localhost: guaranteed connection-refused without a listener
    file_put_contents(SANDBOX . '/hot', 'http://127.0.0.1:1');
}

/** Write a manifest into the sandbox build directory */
function manifest(array $manifest): void
{
    file_put_contents(SANDBOX . '/build/manifest.json', json_encode($manifest));
}

uses()->beforeEach(fn () => setupViteEnv())->in(__DIR__);
