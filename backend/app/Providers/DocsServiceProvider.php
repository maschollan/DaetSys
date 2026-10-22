<?php

namespace App\Providers;

use App\Docs\ApiDocumentation;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

/**
 * Menyuntikkan teks dokumentasi berbahasa Indonesia ke dokumen OpenAPI
 * yang dihasilkan otomatis oleh Scramble.
 *
 * Skema request, skema response, dan daftar error tetap berasal dari kode
 * (Form Request dan API Resource), sedangkan narasi, catatan aturan bisnis,
 * serta pengelompokan endpoint berasal dari {@see ApiDocumentation}.
 */
class DocsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->exposeDocumentRoute();

        Scramble::afterOpenApiGenerated(function (OpenApi $document): void {
            $this->applyInfo($document);
            $this->applyServers($document);
            $this->applyTags($document);
            $this->applyErrorResponses($document);
            $this->applyOperations($document);
        });
    }

    /**
     * Hanya dokumen OpenAPI yang diekspos sebagai route.
     *
     * Antarmuka dokumentasi disajikan oleh Scalar API Reference pada
     * `/docs` (lihat `routes/web.php`), sehingga route UI bawaan Scramble
     * yang bergantung CDN eksternal tidak perlu diaktifkan.
     */
    private function exposeDocumentRoute(): void
    {
        Scramble::configure()->expose(
            ui: false,
            document: fn (Router $router, $action) => $router
                ->get('docs/api.json', $action)
                ->name('scramble.docs.document'),
        );
    }

    /**
     * Judul, versi, dan deskripsi umum API.
     */
    private function applyInfo(OpenApi $document): void
    {
        $info = ApiDocumentation::info();

        $document->info->title = $info['title'];
        $document->info->version = $info['version'];
        $document->info->description = $info['description'];
    }

    /**
     * Server ditulis relatif terhadap origin agar fitur coba request pada
     * halaman dokumentasi selalu mengarah ke instance yang sedang diakses,
     * bukan ke `APP_URL` yang bisa berbeda.
     */
    private function applyServers(OpenApi $document): void
    {
        $server = new Server('/api');
        $server->setDescription('Server API DaetSys pada instance ini.');

        $document->servers = [$server];
    }

    /**
     * Daftar kategori endpoint beserta penjelasannya.
     */
    private function applyTags(OpenApi $document): void
    {
        $document->tags = array_map(
            static fn (string $name, string $description): Tag => new Tag($name, $description),
            array_keys(ApiDocumentation::tags()),
            array_values(ApiDocumentation::tags()),
        );
    }

    /**
     * Mengganti keterangan respons error bawaan berbahasa Inggris dengan
     * keterangan berbahasa Indonesia.
     */
    private function applyErrorResponses(OpenApi $document): void
    {
        $translations = [
            AuthenticationException::class => 'Belum terautentikasi. Token hilang, tidak valid, atau sudah dicabut.',
            AuthorizationException::class => 'Tidak diizinkan. Role tidak berwenang atau aturan pemisahan tugas dilanggar.',
            ValidationException::class => 'Validasi gagal. Rincian per field tersedia pada `errors`.',
            ModelNotFoundException::class => 'Data yang diminta tidak ditemukan.',
        ];

        foreach ($document->components->responses as $name => $response) {
            $key = ltrim((string) $name, '\\');

            if (isset($translations[$key]) && $response instanceof Response) {
                $response->description = $translations[$key];
            }
        }
    }

    /**
     * Ringkasan, deskripsi, pengelompokan, dan header idempotensi per endpoint.
     */
    private function applyOperations(OpenApi $document): void
    {
        $operations = ApiDocumentation::operations();
        $idempotent = ApiDocumentation::idempotentOperations();

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $operationId = $operation->operationId;

                if ($operationId !== null && isset($operations[$operationId])) {
                    $operation->summary($operations[$operationId]['summary']);
                    $operation->description($operations[$operationId]['description']);
                    $operation->setTags($operations[$operationId]['tags']);
                }

                $this->applyParameterDescriptions($operation);
                $this->applySuccessDescriptions($operation);

                if ($operationId !== null && in_array($operationId, $idempotent, true)) {
                    $operation->addParameters([$this->idempotencyHeader()]);
                }
            }
        }
    }

    /**
     * Memberi keterangan pada respons sukses yang semula kosong.
     */
    private function applySuccessDescriptions(Operation $operation): void
    {
        foreach ($operation->responses as $response) {
            if (! $response instanceof Response || $response->code === null) {
                continue;
            }

            $code = (int) $response->code;

            if ($code < 200 || $code >= 300) {
                continue;
            }

            // Keterangan bawaan Scramble hanya menyebut nama kelas resource,
            // misalnya `AssetResource`, sehingga digantikan dengan penjelasan
            // yang lebih berguna bagi pembaca dokumentasi.
            $isPlaceholder = $response->description === ''
                || preg_match('/^`[A-Za-z\\\\]+`$/', $response->description) === 1;

            if (! $isPlaceholder) {
                continue;
            }

            $response->description = match ($code) {
                201 => 'Permintaan berhasil. Data baru dibuat dan dikembalikan pada `data`.',
                202 => 'Permintaan berhasil dan sedang diproses.',
                204 => 'Permintaan berhasil tanpa isi respons.',
                default => 'Permintaan berhasil. Hasil dikembalikan pada `data`.',
            };
        }
    }

    /**
     * Mengganti deskripsi parameter path bawaan berbahasa Inggris dengan
     * keterangan berbahasa Indonesia.
     */
    private function applyParameterDescriptions(Operation $operation): void
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter->in !== 'path') {
                continue;
            }

            $translation = self::pathParameterDescriptions()[$parameter->name] ?? null;

            if ($translation !== null) {
                $parameter->description($translation);
            }
        }
    }

    /**
     * Keterangan berbahasa Indonesia untuk setiap parameter path.
     *
     * @return array<string, string>
     */
    private static function pathParameterDescriptions(): array
    {
        return [
            'asset' => 'ID aset yang akan diproses.',
            'asset_category' => 'ID kategori aset yang akan diproses.',
            'location' => 'ID lokasi penyimpanan yang akan diproses.',
            'transfer' => 'ID transfer yang akan diproses.',
            'activityLog' => 'ID jejak audit yang akan diproses.',
            'user' => 'ID pengguna yang akan diproses.',
            'notification' => 'ID notifikasi milik pengguna yang sedang login.',
            'qr_token' => 'Token QR unik yang tertanam pada aset.',
        ];
    }

    /**
     * Header `Idempotency-Key` untuk endpoint yang mendukung retry.
     */
    private function idempotencyHeader(): Parameter
    {
        $schema = Schema::fromType((new StringType)->setMin(16)->setMax(255));

        return Parameter::make('Idempotency-Key', 'header')
            ->description(
                'Kunci unik (UUID) untuk mencegah request dijalankan dua kali. '
                .'Mengirim ulang dengan kunci yang sama akan mengembalikan hasil tersimpan sebelumnya, bukan membuat data ganda.'
            )
            ->required(false)
            ->example('9f1c1a6e-6b3a-4f0a-9d2e-6a1b2c3d4e5f')
            ->setSchema($schema);
    }
}
