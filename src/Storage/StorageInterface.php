<?php

declare(strict_types=1);

namespace Cenim\Storage;

/**
 * Kontrak driver penyimpanan file.
 *
 * local: LocalDriver — file di folder privat server, di luar web root.
 * s3:    S3Driver    — object storage Tandikat (RustFS), lihat S3Driver.
 */
interface StorageInterface
{
    public function name(): string;

    /**
     * Pindahkan file dari path sementara ke penyimpanan.
     *
     * @param  string $sourcePath   path lokal (hasil upload)
     * @param  string $relativePath path tujuan relatif, mis. uploads/2026/payment_proof/ab12.pdf
     * @return array{driver:string,path:?string,remote_id:?string,remote_url:?string}
     *         (remote_id/remote_url selalu null; kolomnya warisan driver lama)
     */
    public function put(string $sourcePath, string $relativePath, string $mime): array;

    /** Path lokal absolut (driver remote mengunduh ke cache dulu); null bila berkas hilang. */
    public function localPath(array $file): ?string;

    public function delete(array $file): bool;
}
