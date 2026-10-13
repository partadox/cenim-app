<?php

declare(strict_types=1);

namespace Cenim\Core;

use RuntimeException;

/**
 * Render view PHP polos. View hanya menampilkan data — tidak boleh ada SQL di dalamnya.
 *
 *   View::render('admin/dashboard', ['title' => 'Dashboard', 'stats' => $stats], 'admin');
 *
 * Layout menerima $content (HTML view) + semua variabel $data.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        $content = self::capture(CENIM_ROOT . '/views/' . $view . '.php', $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $data['content'] = $content;
        echo self::capture(CENIM_ROOT . '/views/layout/' . $layout . '.php', $data);
    }

    /** Render partial di dalam view lain. */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture(CENIM_ROOT . '/views/' . $view . '.php', $data);
    }

    /** @param array<string,mixed> $data */
    private static function capture(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('View tidak ditemukan: ' . $file);
        }

        $level = ob_get_level();
        ob_start();

        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
