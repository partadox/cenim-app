<?php

declare(strict_types=1);

namespace Cenim\Support;

use RuntimeException;

/**
 * Penulis arsip ZIP dalam PHP murni — tanpa ekstensi zip.
 *
 * Berkas disimpan tanpa kompresi (metode "stored"): isinya PDF, gambar, dan
 * video yang memang sudah terkompresi, jadi ukurannya hampir sama, dan
 * penulisannya streaming (memori tetap kecil untuk berkas besar). Batas format
 * ZIP klasik: 4 GB per arsip dan 65.535 berkas.
 */
final class ZipWriter
{
    /** @var resource */
    private $out;

    /** @var list<array{name:string,crc:int,size:int,offset:int,time:int,date:int}> */
    private array $entries = [];

    public function __construct(private readonly string $path)
    {
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Tidak bisa membuat arsip ZIP: ' . $path);
        }

        $this->out = $handle;
    }

    public function addFile(string $file, string $name): void
    {
        $in = @fopen($file, 'rb');

        if ($in === false) {
            throw new RuntimeException('Tidak bisa membaca ' . $file);
        }

        try {
            $size = (int) filesize($file);
            $crc  = (int) hexdec((string) hash_file('crc32b', $file));

            $this->writeEntry($name, $crc, $size, (int) filemtime($file), static function ($out) use ($in): void {
                if (stream_copy_to_stream($in, $out) === false) {
                    throw new RuntimeException('Gagal menulis arsip ZIP.');
                }
            });
        } finally {
            fclose($in);
        }
    }

    public function addString(string $name, string $data): void
    {
        $this->writeEntry($name, (int) hexdec(hash('crc32b', $data)), strlen($data), time(), static function ($out) use ($data): void {
            fwrite($out, $data);
        });
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** Tulis central directory dan tutup berkas. */
    public function close(): void
    {
        $start = (int) ftell($this->out);

        foreach ($this->entries as $e) {
            fwrite($this->out, pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,   // central file header
                20,           // version made by
                20,           // version needed
                0x0800,       // flag: nama UTF-8
                0,            // stored
                $e['time'],
                $e['date'],
                $e['crc'],
                $e['size'],
                $e['size'],
                strlen($e['name']),
                0,
                0,
                0,
                0,
                0,
                $e['offset']
            ) . $e['name']);
        }

        $size = (int) ftell($this->out) - $start;
        $this->guardSize((int) ftell($this->out));

        fwrite($this->out, pack('VvvvvVVv', 0x06054b50, 0, 0, count($this->entries), count($this->entries), $size, $start, 0));
        fclose($this->out);
    }

    private function writeEntry(string $name, int $crc, int $size, int $mtime, callable $copy): void
    {
        if (count($this->entries) >= 65535) {
            throw new RuntimeException('Arsip ZIP berisi terlalu banyak berkas.');
        }

        $name   = ltrim(str_replace('\\', '/', $name), '/');
        $offset = (int) ftell($this->out);
        [$time, $date] = self::dosTime($mtime);

        $this->guardSize($offset + $size);

        fwrite($this->out, pack(
            'VvvvvvVVVvv',
            0x04034b50,   // local file header
            20,
            0x0800,
            0,
            $time,
            $date,
            $crc,
            $size,
            $size,
            strlen($name),
            0
        ) . $name);

        $copy($this->out);

        $this->entries[] = ['name' => $name, 'crc' => $crc, 'size' => $size, 'offset' => $offset, 'time' => $time, 'date' => $date];
    }

    private function guardSize(int $bytes): void
    {
        if ($bytes >= 0xFFFFFFFF) {
            fclose($this->out);
            @unlink($this->path);
            throw new RuntimeException('Arsip ZIP melebihi 4 GB.');
        }
    }

    /** @return array{0:int,1:int} waktu & tanggal format MS-DOS */
    private static function dosTime(int $timestamp): array
    {
        $d = getdate(max($timestamp, 315532800)); // DOS mulai 1980

        return [
            ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
            (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
        ];
    }
}
