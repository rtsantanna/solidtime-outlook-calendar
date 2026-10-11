<?php

declare(strict_types=1);

namespace App\Service\Import\Importers;

use ZipArchive;

/**
 * Extracts uploaded ZIP archives with limits on file count, total uncompressed
 * size and entry paths, so a small malicious archive can not fill the disk
 * (decompression bomb) or write outside the target directory (zip slip).
 */
class ZipImportHelper
{
    private const int CHUNK_SIZE = 1024 * 1024;

    /**
     * @throws ImportException
     */
    public function extract(string $zipPath, string $targetPath): void
    {
        $zip = new ZipArchive;
        $res = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($res !== true) {
            throw new ImportException(__('Invalid ZIP, error code: :code', ['code' => $res]));
        }

        try {
            $maxFiles = (int) config('import.zip_max_files');
            $maxUncompressedSize = (int) config('import.zip_max_uncompressed_size');

            if ($zip->numFiles > $maxFiles) {
                throw new ImportException(__('ZIP contains too many files, maximum is :max', ['max' => $maxFiles]));
            }

            // Check the sizes declared in the archive before writing anything to disk
            $declaredSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    throw new ImportException(__('Invalid ZIP entry'));
                }
                $this->validateEntryName($stat['name']);
                $declaredSize += $stat['size'];
                if ($declaredSize > $maxUncompressedSize) {
                    throw new ImportException(__('ZIP uncompressed size exceeds the maximum of :max bytes', ['max' => $maxUncompressedSize]));
                }
            }

            // The declared sizes can be forged, so the written bytes are counted as well
            $writtenSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    throw new ImportException(__('Invalid ZIP entry'));
                }
                $name = $stat['name'];
                $entryPath = $targetPath.DIRECTORY_SEPARATOR.$name;

                if (str_ends_with($name, '/')) {
                    $this->ensureDirectoryExists($entryPath);

                    continue;
                }
                $this->ensureDirectoryExists(dirname($entryPath));

                $stream = $zip->getStreamIndex($index);
                if ($stream === false) {
                    throw new ImportException(__('ZIP entry ":name" can not be read', ['name' => $name]));
                }
                $target = fopen($entryPath, 'wb');
                if ($target === false) {
                    fclose($stream);
                    throw new ImportException(__('ZIP entry ":name" can not be extracted', ['name' => $name]));
                }
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, self::CHUNK_SIZE);
                        if ($chunk === false) {
                            throw new ImportException(__('ZIP entry ":name" can not be read', ['name' => $name]));
                        }
                        $writtenSize += strlen($chunk);
                        if ($writtenSize > $maxUncompressedSize) {
                            throw new ImportException(__('ZIP uncompressed size exceeds the maximum of :max bytes', ['max' => $maxUncompressedSize]));
                        }
                        fwrite($target, $chunk);
                    }
                } finally {
                    fclose($target);
                    fclose($stream);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @throws ImportException
     */
    private function validateEntryName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/')) {
            throw new ImportException(__('ZIP contains an invalid file path: ":name"', ['name' => $name]));
        }
        if (preg_match('/^[a-zA-Z]:/', $name) === 1) {
            throw new ImportException(__('ZIP contains an invalid file path: ":name"', ['name' => $name]));
        }
        foreach (explode('/', rtrim($name, '/')) as $segment) {
            if ($segment === '' || $segment === '..') {
                throw new ImportException(__('ZIP contains an invalid file path: ":name"', ['name' => $name]));
            }
        }
    }

    /**
     * @throws ImportException
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new ImportException(__('Directory ":path" can not be created', ['path' => $path]));
        }
    }
}
