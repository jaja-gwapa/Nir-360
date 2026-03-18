<?php
declare(strict_types=1);

class RBACMiddleware
{
    /** Allowed roles per route prefix */
    private array $rules = [
        '/admin/' => ['admin'],
        '/responder/' => ['responder'],
        '/user/' => ['user'],
    ];

    public function __invoke(string $path, ?int $userId, ?string $role): bool
    {
        if ($userId === null || $role === null) {
            return false;
        }

        foreach ($this->rules as $prefix => $allowedRoles) {
            if (str_starts_with($path, $prefix)) {
                return in_array($role, $allowedRoles, true);
            }
        }

        return true;
    }

    public function requireRole(string $path): ?string
    {
        foreach (array_keys($this->rules) as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $this->rules[$prefix][0] ?? null;
            }
        }
        return null;
    }
}
