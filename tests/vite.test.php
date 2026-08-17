<?php

use Leaf\Vite;

const APP_JS_MANIFEST = [
    'app.js' => [
        'src' => 'app.js',
        'file' => 'assets/app.abc123.js',
        'css' => ['assets/app.def456.css'],
    ],
    'app.css' => [
        'src' => 'app.css',
        'file' => 'assets/app.def456.css',
    ],
];

test('isRunningHot reflects the presence of the hot file', function () {
    expect(Vite::isRunningHot())->toBeFalse();

    hotServer();

    expect(Vite::isRunningHot())->toBeTrue();
});

test('build in hot mode points tags at the dev server with the vite client', function () {
    hotServer('http://localhost:5173');

    $html = (string) Vite::build('app.js');

    expect($html)
        ->toContain('<script type="module" src="http://localhost:5173/@vite/client"></script>')
        ->toContain('<script type="module" src="http://localhost:5173/app.js"></script>');
});

test('build renders script, stylesheet and preload tags from the manifest', function () {
    manifest(APP_JS_MANIFEST);

    $html = (string) Vite::build('app.js');

    expect($html)
        ->toContain('<script type="module" src="/assets/assets/app.abc123.js"></script>')
        ->toContain('<link rel="stylesheet" href="/assets/assets/app.def456.css" />')
        ->toContain('rel="modulepreload"')
        ->toContain('rel="preload"');
});

test('build resolves imported chunks and their css', function () {
    manifest([
        'app.js' => [
            'src' => 'app.js',
            'file' => 'assets/app.abc123.js',
            'imports' => ['_shared.js'],
        ],
        '_shared.js' => [
            'file' => 'assets/shared.fff000.js',
            'css' => ['assets/shared.ccc999.css'],
        ],
        'shared.css' => [
            'file' => 'assets/shared.ccc999.css',
        ],
    ]);

    $html = (string) Vite::build('app.js');

    expect($html)
        ->toContain('assets/shared.fff000.js')
        ->toContain('<link rel="stylesheet" href="/assets/assets/shared.ccc999.css" />');
});

test('build throws for a missing manifest and for an unknown entrypoint', function () {
    expect(fn () => Vite::build('app.js'))->toThrow(Exception::class, 'Vite manifest not found');

    manifest(APP_JS_MANIFEST);
    expect(fn () => Vite::build('missing.js'))->toThrow(Exception::class, 'Unable to locate file in Vite manifest');
});

test('asset returns the hashed path from the manifest', function () {
    manifest(APP_JS_MANIFEST);

    expect(Vite::asset('app.js'))->toBe('/assets/assets/app.abc123.js');
});

test('asset points at the dev server in hot mode', function () {
    hotServer('http://localhost:5173');

    expect(Vite::asset('app.js'))->toBe('http://localhost:5173/app.js');
});

test('config overrides paths individually or as an array', function () {
    Vite::config('assets', '/static');
    manifest(APP_JS_MANIFEST);

    expect(Vite::asset('app.js'))->toBe('/static/assets/app.abc123.js');

    Vite::config(['assets' => 'https://cdn.example.com/']);

    expect(Vite::asset('app.css'))->toBe('https://cdn.example.com/assets/app.def456.css');
});

test('a csp nonce is applied to generated tags', function () {
    manifest(APP_JS_MANIFEST);

    $nonce = Vite::useCspNonce('test-nonce');

    expect($nonce)->toBe('test-nonce');
    expect(Vite::cspNonce())->toBe('test-nonce');
    expect((string) Vite::build('app.js'))->toContain('nonce="test-nonce"');
});

test('useCspNonce generates a random nonce when none is given', function () {
    expect(strlen(Vite::useCspNonce()))->toBe(40);
});

test('integrity hashes are read from the manifest via the integrity key', function () {
    manifest([
        'app.js' => [
            'src' => 'app.js',
            'file' => 'assets/app.abc123.js',
            'integrity' => 'sha384-hash',
        ],
    ]);

    expect((string) Vite::build('app.js'))->toContain('integrity="sha384-hash"');
});

test('useIntegrityKey(false) disables integrity lookups', function () {
    manifest([
        'app.js' => [
            'src' => 'app.js',
            'file' => 'assets/app.abc123.js',
            'integrity' => 'sha384-hash',
        ],
    ]);

    Vite::useIntegrityKey(false);

    expect((string) Vite::build('app.js'))->not->toContain('integrity=');
});

test('script and style tag attribute resolvers are applied', function () {
    manifest(APP_JS_MANIFEST);

    Vite::useScriptTagAttributes(['data-turbo-track' => 'reload']);
    Vite::useStyleTagAttributes(fn ($src) => ['data-src' => $src]);

    $html = (string) Vite::build('app.js');

    expect($html)
        ->toContain('data-turbo-track="reload"')
        ->toContain('data-src="app.css"');
});

test('preload tags can be suppressed by a resolver returning false', function () {
    manifest(APP_JS_MANIFEST);

    Vite::usePreloadTagAttributes(false);

    $html = (string) Vite::build('app.js');

    expect($html)->not->toContain('rel="modulepreload"');
    expect(Vite::preloadedAssets())->toBe([]);
});

test('preloaded assets are tracked by url', function () {
    manifest(APP_JS_MANIFEST);

    Vite::build('app.js');

    expect(array_keys(Vite::preloadedAssets()))->toContain('/assets/assets/app.abc123.js');
});

test('useManifestFilename reads an alternative manifest file', function () {
    file_put_contents(SANDBOX . '/build/custom.json', json_encode(APP_JS_MANIFEST));

    Vite::useManifestFilename('custom.json');

    expect(Vite::asset('app.js'))->toBe('/assets/assets/app.abc123.js');
});

test('manifestHash fingerprints the manifest file', function () {
    expect(Vite::manifestHash())->toBeNull();

    manifest(APP_JS_MANIFEST);

    expect(Vite::manifestHash())->toBe(md5_file(SANDBOX . '/build/manifest.json'));

    hotServer();
    expect(Vite::manifestHash())->toBeNull();
});

test('reactRefresh outputs the preamble only in hot mode', function () {
    expect(Vite::reactRefresh())->toBeNull();

    hotServer('http://localhost:5173');

    expect((string) Vite::reactRefresh())
        ->toContain('http://localhost:5173/@react-refresh')
        ->toContain('__vite_plugin_react_preamble_installed__');
});

test('the vite() helper prefixes entrypoints with the configured views path', function () {
    hotServer('http://localhost:5173');

    expect((string) vite('app.js'))
        ->toContain('src="http://localhost:5173/app/views/app.js"');

    expect((string) vite(['a.js', 'app/views/b.js'], 'app/views'))
        ->toContain('src="http://localhost:5173/app/views/a.js"')
        ->toContain('src="http://localhost:5173/app/views/b.js"');
});

test('default paths are cwd-relative so lite apps work unconfigured', function () {
    // '/hot' and '/build' resolved from the disk root, so hot mode could
    // never activate in a lite app — MVC always overrides these at boot
    $defaults = (new ReflectionClass(Vite::class))->getDefaultProperties()['paths'];

    expect($defaults['hotFile'])->toBe('hot')
        ->and($defaults['build'])->toBe('build')
        ->and($defaults['assets'])->toStartWith('/'); // a URL prefix, not a file path
});

test('per-page inertia entries resolve from dynamic manifest chunks', function () {
    // production inertia templates request @vite(['app.js', 'pages/welcome.jsx']):
    // only app.js is a build INPUT — pages land in the manifest as dynamic
    // chunks via import.meta.glob, and build() must find them there
    manifest([
        'app.js' => [
            'src' => 'app.js',
            'file' => 'assets/app.abc123.js',
        ],
        'pages/welcome.jsx' => [
            'src' => 'pages/welcome.jsx',
            'file' => 'assets/welcome.xyz789.js',
            'isDynamicEntry' => true,
        ],
    ]);

    $html = (string) Vite::build(['app.js', 'pages/welcome.jsx']);

    expect($html)->toContain('assets/app.abc123.js')
        ->and($html)->toContain('assets/welcome.xyz789.js');
});
