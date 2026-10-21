<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\AuditContext;
use App\Support\AuditMetadata;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * The single entry point for writing audit entries.
 *
 * - Fills in the actor, IP, user agent and request id by itself ("system" for scheduler / queue work).
 * - Does NOT open its own transaction: call it inside the same DB::transaction as the action it records,
 *   so both succeed or both roll back.
 * - Use tryLog() only for events that must never block what the user is doing (sign-ins).
 */
class AuditLogger
{
    /** Highest first. */
    private const STAFF_PRIORITY = ['platform_admin', 'org_admin', 'counselor'];

    public function __construct(private AuditContext $context) {}

    /**
     * Record one event.
     *
     * @param  User|'system'|null  $actor  null = whoever is signed in (or "system" outside a web request)
     * @param  array{organization_id?: int|null, target_name?: string|null, target_type?: string, target_id?: int|null, at?: CarbonInterface, ip?: string|null, user_agent?: string|null, request_id?: string, actor_type?: 'guest'}  $options
     *                                                                                                                                                                                                  `at`, `ip`, `user_agent`, `request_id` and `actor_type` override what is read from the request — meant for seeders and tests only.
     */
    public function log(string $action, ?Model $target = null, array $metadata = [], User|string|null $actor = null, array $options = []): AuditLog
    {
        // Array lookup, not config("audit.actions.{$action}"): the dots in action keys would be read as nesting.
        $definition = config('audit.actions', [])[$action] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown audit action [{$action}]. Add it to config/audit.php first.");
        }

        $category = $definition['category'];
        [$actorType, $user] = ($options['actor_type'] ?? null) === 'guest' ? ['guest', null] : $this->resolveActor($actor);

        $role = $user ? $this->roleOf($user, $category) : null;
        $isStaff = $role !== null && $this->roleCountsAsStaff($role, $category);
        $web = $this->hasHttpRequest();

        $row = [
            'actor_type' => $actorType,
            'actor_user_id' => $user?->getKey(),
            // Name + role are copied only for staff. Everyone else is a bare id (personal data must vanish with the account).
            'actor_name' => $isStaff ? Str::limit($user->name, 255, '') : null,
            'actor_role' => $isStaff ? $role : null,
            'action' => $action,
            'category' => $category,
            'metadata' => ($clean = AuditMetadata::sanitize($metadata)) ?: null,
            'ip' => array_key_exists('ip', $options) ? $options['ip'] : ($web ? request()->ip() : null),
            // Sign-in noise from non-staff keeps only user id + IP.
            'user_agent' => array_key_exists('user_agent', $options)
                ? $options['user_agent']
                : ($web && ! ($category === 'auth' && ! $isStaff) ? Str::limit((string) request()->userAgent(), 255, '') : null),
            'request_id' => $options['request_id'] ?? $this->context->requestId(),
        ];

        $row += $this->targetColumns($target, $options, $category);

        if (isset($options['organization_id'])) {
            $row['organization_id'] = $options['organization_id'];
        }

        if (isset($options['at'])) {
            $row['created_at'] = $options['at']->copy()->utc();
        }

        return AuditLog::create($row);
    }

    /**
     * Like log(), but a failure is reported and swallowed. Only for events that must not block the user
     * (sign-in, failed sign-in). Admin actions must use log() inside their transaction.
     */
    public function tryLog(string $action, ?Model $target = null, array $metadata = [], User|string|null $actor = null, array $options = []): ?AuditLog
    {
        try {
            return $this->log($action, $target, $metadata, $actor, $options);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Run $callback with the "system" actor (scheduled jobs, queue workers). */
    public function asSystem(callable $callback): mixed
    {
        return $this->context->asSystem($callback);
    }

    // ---- internals -------------------------------------------------------------------------

    /** @return array{0: string, 1: ?User} */
    private function resolveActor(User|string|null $actor): array
    {
        if ($actor === 'system') {
            return ['system', null];
        }

        if ($actor instanceof User) {
            return ['user', $actor];
        }

        if ($this->context->isSystem()) {
            return ['system', null];
        }

        if ($user = auth()->user()) {
            return ['user', $user];
        }

        // Nobody signed in: a web request is a visitor; anything else (scheduler, queue, console) is the system.
        return [$this->hasHttpRequest() ? 'guest' : 'system', null];
    }

    private function hasHttpRequest(): bool
    {
        return app()->bound('request') && request()->route() !== null;
    }

    /** The role the user acted in: the active session role in a web request, otherwise their highest staff membership. */
    private function roleOf(User $user, string $category): ?string
    {
        if ($category !== 'auth' && $this->hasHttpRequest() && auth()->id() === $user->getKey() && session()->has('current_role')) {
            return (string) session('current_role');
        }

        return $this->highestStaffRole($user);
    }

    private function highestStaffRole(User $user): ?string
    {
        $roles = $user->memberships()
            ->where('status', MembershipStatus::Active->value)
            ->get()
            ->map(fn ($m) => $m->role->value)
            ->all();

        foreach (self::STAFF_PRIORITY as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return $roles[0] ?? null;
    }

    /** Staff: platform admins and org admins always; counselors only for administrative (non sign-in) actions. */
    private function roleCountsAsStaff(string $role, string $category): bool
    {
        if (! in_array($role, config('audit.staff_roles', self::STAFF_PRIORITY), true)) {
            return false;
        }

        return ! ($role === 'counselor' && $category === 'auth');
    }

    /** Same staff rule as for actors, so a counselor's sign-in is never stored with their name either. */
    private function isStaffUser(User $user, string $category): bool
    {
        $role = $this->highestStaffRole($user);

        return $role !== null && $this->roleCountsAsStaff($role, $category);
    }

    /** @return array<string, mixed> */
    private function targetColumns(?Model $target, array $options, string $category): array
    {
        $columns = [];

        if ($target === null && ! isset($options['target_type'])) {
            return [];
        }

        if ($target !== null) {
            $columns['target_type'] = (config('audit.target_types', [])[$target::class] ?? null) ?? Str::snake(class_basename($target));
            $columns['target_id'] = $target->getKey();
            $columns['target_name'] = array_key_exists('target_name', $options) ? $options['target_name'] : $this->targetName($target, $category);

            if (! isset($options['organization_id'])) {
                $columns['organization_id'] = $target instanceof Organization
                    ? $target->getKey()
                    : ($target->getAttribute('org_id') ?: null);
            }
        } else {
            // A target that no longer exists as a model (e.g. already deleted): the caller describes it.
            $columns['target_type'] = $options['target_type'];
            $columns['target_id'] = $options['target_id'] ?? null;
            $columns['target_name'] = $options['target_name'] ?? null;
        }

        if (is_string($columns['target_name'] ?? null)) {
            $columns['target_name'] = Str::limit($columns['target_name'], 255, '');
        }

        return $columns;
    }

    /** A copy of the target's name at this moment, so the entry stays readable after a rename or delete. */
    private function targetName(Model $target, string $category): ?string
    {
        // Non-staff people are stored by id only.
        if ($target instanceof User) {
            return $this->isStaffUser($target, $category) ? $target->name : null;
        }

        if (method_exists($target, 'auditName')) {
            return $target->auditName();
        }

        $name = $target->getAttribute('name') ?? $target->getAttribute('title');

        return is_string($name) ? $name : null;
    }
}
