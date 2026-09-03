<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class JwtGenerateKeysCommand extends Command
{
    protected $signature = 'jwt:generate-keys {--bits=2048 : RSA key size} {--force : Overwrite existing keys}';
    protected $description = 'Generate RS256 (and EdDSA) keypairs for JWT, publish JWKS, keep private key server-side';

    public function handle(): int
    {
        $privatePem = storage_path('certs/jwt-private.pem');
        $publicPem = storage_path('certs/jwt-public.pem');
        $eddsaPrivate = storage_path('certs/jwt-eddsa-private.pem');
        $eddsaPublic = storage_path('certs/jwt-eddsa-public.pem');

        if (! is_dir(dirname($privatePem))) {
            mkdir(dirname($privatePem), 0755, true);
        }

        $force = (bool) $this->option('force');
        $bits = (int) $this->option('bits');

        if (file_exists($privatePem) && ! $force) {
            $this->warn('RS256 private key already exists. Use --force to overwrite.');
        } else {
            $this->info("Generating RS256 {$bits}-bit keypair...");
            $priv = openssl_pkey_new([
                'private_key_bits' => $bits,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            if (! $priv) {
                $this->error('Failed to generate RSA key: ' . openssl_error_string());
                return 1;
            }
            openssl_pkey_export($priv, $privateOut);
            $pub = openssl_pkey_get_details($priv);
            file_put_contents($privatePem, $privateOut);
            file_put_contents($publicPem, $pub['key']);
            chmod($privatePem, 0600);
            chmod($publicPem, 0644);
            $this->info("  private: {$privatePem}");
            $this->info("  public : {$publicPem}");
        }

        if (file_exists($eddsaPrivate) && ! $force) {
            $this->warn('EdDSA private key already exists. Use --force to overwrite.');
        } else {
            $this->info('Generating Ed25519 keypair...');
            $priv = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'Ed25519'] ?? []);
            // Fallback via openssl CLI for Ed25519 if pkey_new fails
            if (! $priv) {
                // Try via genpkey
                $tmp = tempnam(sys_get_temp_dir(), 'eddsa');
                exec('openssl genpkey -algorithm ED25519 -out ' . escapeshellarg($tmp) . ' 2>&1', $out, $ret);
                if ($ret === 0 && file_exists($tmp)) {
                    $privPem = file_get_contents($tmp);
                    file_put_contents($eddsaPrivate, $privPem);
                    exec('openssl pkey -in ' . escapeshellarg($tmp) . ' -pubout -out ' . escapeshellarg($eddsaPublic) . ' 2>&1');
                    chmod($eddsaPrivate, 0600);
                    chmod($eddsaPublic, 0644);
                    unlink($tmp);
                    $this->info("  private: {$eddsaPrivate}");
                    $this->info("  public : {$eddsaPublic}");
                } else {
                    $this->warn('EdDSA generation not available on this OpenSSL build, skipping.');
                }
            } else {
                openssl_pkey_export($priv, $privateOut);
                $pub = openssl_pkey_get_details($priv);
                file_put_contents($eddsaPrivate, $privateOut);
                file_put_contents($eddsaPublic, $pub['key']);
                chmod($eddsaPrivate, 0600);
                chmod($eddsaPublic, 0644);
                $this->info("  private: {$eddsaPrivate}");
                $this->info("  public : {$eddsaPublic}");
            }
        }

        Cache::forget('jwks');
        $this->info('JWKS cache cleared.');
        $this->info('Done. Private keys kept server-side; public JWKS at /.well-known/jwks.json');
        return 0;
    }
}
