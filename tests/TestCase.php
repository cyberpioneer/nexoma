<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Passport;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private static ?string $passportKeyDirectory = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests verwenden nie die Schluessel aus storage/, sondern ein temporaeres Schluesselpaar.
        Passport::loadKeysFrom(self::passportKeyDirectory());
    }

    /**
     * Liefert ein Verzeichnis mit einem pro Testlauf erzeugten RSA-Schluesselpaar (wird am Ende geloescht).
     */
    private static function passportKeyDirectory(): string
    {
        if (self::$passportKeyDirectory !== null) {
            return self::$passportKeyDirectory;
        }

        $directory = sys_get_temp_dir().'/nexora-passport-test-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false || ! openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('Passport-Testschluessel konnten nicht erzeugt werden.');
        }

        file_put_contents($directory.'/oauth-private.key', $privateKey);
        file_put_contents($directory.'/oauth-public.key', openssl_pkey_get_details($key)['key']);
        chmod($directory.'/oauth-private.key', 0600);
        chmod($directory.'/oauth-public.key', 0600);

        register_shutdown_function(static function () use ($directory): void {
            array_map('unlink', glob($directory.'/*') ?: []);
            @rmdir($directory);
        });

        return self::$passportKeyDirectory = $directory;
    }
}
