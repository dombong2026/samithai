<?php

/**
 * First-boot secure environment bootstrap for the web installer.
 * Never runs after a successful installation.
 */
$root = dirname(__DIR__);
$installed = $root.'/storage/app/installed.lock';

if (! is_file($installed)) {
    $envPath = $root.'/.env';
    if (! is_file($envPath) && ! copy($root.'/.env.example', $envPath)) {
        throw new RuntimeException('Create a writable .env file from .env.example before installation.');
    }

    $handle = fopen($envPath, 'c+');
    if (! $handle || ! flock($handle, LOCK_EX)) {
        throw new RuntimeException('Cannot lock the environment file for secure initialization.');
    }

    try {
        rewind($handle);
        $contents = stream_get_contents($handle);
        $changed = false;

        if (! preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m', $contents)) {
            $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $contents);
            $changed = true;
        }
        if (! preg_match('/^INSTALL_TOKEN=[a-f0-9]{48}$/m', $contents)) {
            $token = bin2hex(random_bytes(24));
            $contents .= "\nINSTALL_TOKEN=".$token."\n";
            $changed = true;
        }

        foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync'] as $name => $value) {
            $pattern = '/^'.preg_quote($name, '/').'=.*$/m';
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $name.'='.$value, $contents);
            } else {
                $contents .= "\n".$name.'='.$value."\n";
            }
            $changed = true;
        }

        if ($changed) {
            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $contents) !== strlen($contents)) {
                throw new RuntimeException('Unable to initialize .env. Check filesystem permissions.');
            }
            fflush($handle);
        }
        @chmod($envPath, 0600);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
