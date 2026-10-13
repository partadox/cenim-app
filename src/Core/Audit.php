<?php

declare(strict_types=1);

namespace Cenim\Core;

use Cenim\Domain\EditionRepo;

/**
 * Jejak audit untuk setiap aksi penting (login, verifikasi, penerbitan dokumen).
 * Sengaja tidak pernah melempar exception: kegagalan menulis audit tidak boleh
 * membatalkan aksi utama, cukup dicatat ke log file.
 */
final class Audit
{
    /**
     * @param array{
     *     actor_type?: string, actor_id?: string|int|null,
     *     target_type?: string, target_id?: int|null,
     *     meta?: array<string,mixed>, edition_id?: int|null
     * } $options
     */
    public static function log(string $action, array $options = []): void
    {
        try {
            if (!Db::tableExists('audit_logs')) {
                return;
            }

            [$actorType, $actorId] = self::resolveActor($options);

            Db::insert('audit_logs', [
                'edition_id'  => $options['edition_id'] ?? (EditionRepo::contextId() ?: null),
                'actor_type'  => $actorType,
                'actor_id'    => $actorId === null ? null : (string) $actorId,
                'action'      => $action,
                'target_type' => $options['target_type'] ?? null,
                'target_id'   => $options['target_id'] ?? null,
                'meta_json'   => isset($options['meta'])
                    ? json_encode($options['meta'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null,
                'ip'          => PHP_SAPI === 'cli' ? 'cli' : client_ip(),
                'ua'          => user_agent(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Gagal menulis audit log', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /** @return array{0:string,1:string|int|null} */
    private static function resolveActor(array $options): array
    {
        if (isset($options['actor_type'])) {
            return [(string) $options['actor_type'], $options['actor_id'] ?? null];
        }

        if (PHP_SAPI === 'cli') {
            return ['system', 'cli'];
        }

        if (AdminAuth::isLoggedIn()) {
            return ['admin', 'admin'];
        }

        // Aktor author/panitia dicatat dengan email-nya (identitas login).
        $email = AuthorAuth::email();
        if ($email !== null) {
            return ['author', $email];
        }

        return ['system', null];
    }
}
