<?php

/**
 * OOP-обёртка над существующими require_auth/require_admin/require_minec.
 * Позволяет контроллерам работать через инстанс вместо глобальных функций.
 */
final class SessionGuard
{
    public function requireAuth(): void
    {
        require_auth();
    }

    public function requireAdmin(): void
    {
        require_admin();
    }

    public function requireMinec(): void
    {
        require_minec();
    }

    public function userId(): ?int
    {
        return current_user_id();
    }

    public function role(): string
    {
        return $_SESSION['role'] ?? '';
    }

    public function isAdmin(): bool
    {
        return is_admin();
    }

    public function isMinec(): bool
    {
        return is_minec();
    }

    public function municipalityName(): ?string
    {
        return current_municipality_name();
    }

    public function municipalityId(): ?int
    {
        return isset($_SESSION['municipality_id']) ? (int)$_SESSION['municipality_id'] : null;
    }
}
