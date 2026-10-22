<?php

namespace App\Console\Commands;

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;

/**
 * Mengekspor dokumen OpenAPI menjadi berkas Markdown berbahasa Indonesia.
 *
 * Berkas yang dihasilkan selalu mengikuti kode terbaru karena sumbernya
 * adalah dokumen OpenAPI yang sama dengan yang dipakai halaman
 * dokumentasi interaktif (`/docs`).
 */
class ExportApiDocsCommand extends Command
{
    /**
     * Nama perintah artisan.
     */
    protected $signature = 'daetsys:docs {--output=docs/DAFTAR_ENDPOINT_API.md : Lokasi berkas keluaran}';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Ekspor daftar endpoint API beserta parameter dan responsnya ke berkas Markdown.';

    /**
     * Menjalankan perintah.
     */
    public function handle(): int
    {
        $spec = $this->openApiDocument();

        if ($spec === null) {
            $this->error('Dokumen OpenAPI tidak dapat dibuat.');

            return self::FAILURE;
        }

        $path = base_path((string) $this->option('output'));
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $this->render($spec));

        $this->info("Dokumentasi API ditulis ke {$this->option('output')}.");

        return self::SUCCESS;
    }

    /**
     * Dokumen OpenAPI dalam bentuk array.
     *
     * @return array<string, mixed>|null
     */
    private function openApiDocument(): ?array
    {
        $generator = app(CacheableGenerator::class);

        $document = $generator(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

        return is_array($document) ? $document : null;
    }

    /**
     * Menyusun isi Markdown dari dokumen OpenAPI.
     *
     * @param  array<string, mixed>  $spec
     */
    private function render(array $spec): string
    {
        $lines = [];

        $lines[] = '# '.$spec['info']['title'].' - Daftar Endpoint';
        $lines[] = '';
        $lines[] = '> Dokumen ini dibuat otomatis dari kode aplikasi.';
        $lines[] = '> Jalankan `php artisan daetsys:docs` untuk memperbaruinya.';
        $lines[] = '> Untuk mencoba request secara interaktif, buka `/docs` pada aplikasi yang sedang berjalan.';
        $lines[] = '';
        $lines[] = '**Versi API:** '.$spec['info']['version'];
        $lines[] = '';

        $description = (string) ($spec['info']['description'] ?? '');

        if ($description !== '') {
            $lines[] = $description;
            $lines[] = '';
        }

        $lines = array_merge($lines, $this->renderIndex($spec));
        $lines = array_merge($lines, $this->renderEndpoints($spec));

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * Daftar isi per kelompok endpoint.
     *
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function renderIndex(array $spec): array
    {
        $tags = [];

        foreach ($spec['tags'] ?? [] as $tag) {
            $tags[(string) $tag['name']] = (string) ($tag['description'] ?? '');
        }

        $lines = ['## Daftar Isi', ''];

        foreach ($tags as $name => $tagDescription) {
            $lines[] = '### '.$name;
            $lines[] = '';

            if ($tagDescription !== '') {
                $lines[] = $tagDescription;
                $lines[] = '';
            }

            foreach ($this->operationsByTag($spec, $name) as $operations) {
                foreach ($operations as [$method, $path, $operation]) {
                    $lines[] = '- `'.$method.' '.$path.'` - '.($operation['summary'] ?? '');
                }
            }

            $lines[] = '';
        }

        return $lines;
    }

    /**
     * Seluruh endpoint beserta detail parameter dan responsnya.
     *
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function renderEndpoints(array $spec): array
    {
        $lines = ['## Detail Endpoint', ''];

        foreach ($this->operationsByTag($spec) as $tag => $operations) {
            $lines[] = '### '.$tag;
            $lines[] = '';

            foreach ($operations as [$method, $path, $operation]) {
                $lines = array_merge($lines, $this->renderOperation($method, $path, $operation, $spec));
            }
        }

        return $lines;
    }

    /**
     * Mengelompokkan seluruh operation berdasarkan tag.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, list<array{0: string, 1: string, 2: array<string, mixed>}>>
     */
    private function operationsByTag(array $spec, ?string $only = null): array
    {
        $grouped = [];

        foreach ($spec['paths'] ?? [] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if ($method === 'parameters' || ! is_array($operation)) {
                    continue;
                }

                $tags = $operation['tags'] ?? ['Lainnya'];
                $tag = (string) ($tags[0] ?? 'Lainnya');

                if ($only !== null && $tag !== $only) {
                    continue;
                }

                $grouped[$tag][] = [strtoupper((string) $method), (string) $path, $operation];
            }
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Detail satu endpoint.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function renderOperation(string $method, string $path, array $operation, array $spec): array
    {
        $lines = [];

        $lines[] = '#### '.$method.' '.$path;
        $lines[] = '';

        if (! empty($operation['summary'])) {
            $lines[] = (string) $operation['summary'];
            $lines[] = '';
        }

        if (! empty($operation['description'])) {
            $lines[] = (string) $operation['description'];
            $lines[] = '';
        }

        $lines = array_merge($lines, $this->renderParameters($operation));
        $lines = array_merge($lines, $this->renderRequestBody($operation, $spec));
        $lines = array_merge($lines, $this->renderResponses($operation, $spec));

        return $lines;
    }

    /**
     * Tabel parameter path, query, dan header.
     *
     * @param  array<string, mixed>  $operation
     * @return list<string>
     */
    private function renderParameters(array $operation): array
    {
        $parameters = $operation['parameters'] ?? [];

        if ($parameters === []) {
            return [];
        }

        $lines = ['**Parameter**', ''];

        $lines[] = '| Nama | Lokasi | Tipe | Wajib | Keterangan |';
        $lines[] = '| --- | --- | --- | :---: | --- |';

        foreach ($parameters as $parameter) {
            $lines[] = '| `'.($parameter['name'] ?? '').'`'
                .' | '.($parameter['in'] ?? '')
                .' | '.($this->schemaType($parameter['schema'] ?? null))
                .' | '.(! empty($parameter['required']) ? 'Ya' : 'Tidak')
                .' | '.$this->escapeCell((string) ($parameter['description'] ?? ''))
                .' |';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * Tabel field body request beserta aturan validasinya.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function renderRequestBody(array $operation, array $spec): array
    {
        $body = $operation['requestBody'] ?? null;

        if (! is_array($body)) {
            return [];
        }

        $schema = $body['content']['application/json']['schema'] ?? null;
        $resolved = $this->resolveSchema($schema, $spec);

        if ($resolved === null) {
            return [];
        }

        $required = (array) ($resolved['required'] ?? []);

        $lines = ['**Body Request** (`application/json`)', ''];

        $lines[] = '| Field | Tipe | Wajib | Keterangan |';
        $lines[] = '| --- | --- | :---: | --- |';

        foreach ($resolved['properties'] ?? [] as $name => $property) {
            $lines[] = '| `'.$name.'`'
                .' | '.($this->schemaType($property))
                .' | '.(in_array($name, $required, true) ? 'Ya' : 'Tidak')
                .' | '.$this->escapeCell((string) ($property['description'] ?? ''))
                .' |';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * Daftar kode status dan deskripsi responsnya.
     *
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $spec
     * @return list<string>
     */
    private function renderResponses(array $operation, array $spec): array
    {
        $responses = $operation['responses'] ?? [];

        if ($responses === []) {
            return [];
        }

        $lines = ['**Respons**', ''];

        $lines[] = '| Kode | Keterangan |';
        $lines[] = '| --- | --- |';

        foreach ($responses as $status => $response) {
            $description = is_array($response)
                ? (string) ($response['description'] ?? '')
                : (string) $response;

            if (is_array($response) && isset($response['$ref'])) {
                $description = $this->resolveResponseDescription((string) $response['$ref'], $spec) ?? $description;
            }

            $lines[] = '| `'.$status.'` | '.$this->escapeCell($description).' |';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * Mengambil deskripsi respons yang direferensikan melalui `$ref`.
     *
     * @param  array<string, mixed>  $spec
     */
    private function resolveResponseDescription(string $ref, array $spec): ?string
    {
        $name = str_contains($ref, '/') ? substr($ref, strrpos($ref, '/') + 1) : $ref;

        $description = $spec['components']['responses'][$name]['description'] ?? null;

        return is_string($description) ? $description : null;
    }

    /**
     * Menelusuri referensi `$ref` pada skema.
     *
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    private function resolveSchema(?array $schema, array $spec): ?array
    {
        if ($schema === null) {
            return null;
        }

        if (! isset($schema['$ref'])) {
            return $schema;
        }

        $ref = (string) $schema['$ref'];
        $name = str_contains($ref, '/') ? substr($ref, strrpos($ref, '/') + 1) : $ref;

        return $spec['components']['schemas'][$name] ?? null;
    }

    /**
     * Menentukan tipe data skema untuk ditampilkan pada tabel.
     *
     * @param  array<string, mixed>|null  $schema
     */
    private function schemaType(?array $schema): string
    {
        if ($schema === null) {
            return '-';
        }

        $type = $schema['type'] ?? 'object';

        if (is_array($type)) {
            $type = implode(' | ', array_filter($type));
        }

        if ($type === 'object' && isset($schema['properties'])) {
            return 'object';
        }

        if (isset($schema['enum']) && $schema['enum'] !== []) {
            return $type.' (`'.implode('`, `', array_map(
                static fn ($value): string => is_scalar($value) ? (string) $value : 'null',
                $schema['enum'],
            )).'`)';
        }

        return (string) $type;
    }

    /**
     * Membersihkan karakter pipe agar aman dipakai pada tabel Markdown.
     */
    private function escapeCell(string $value): string
    {
        return str_replace(["\r\n", "\n", '|'], [' ', ' ', '\\|'], $value);
    }
}
