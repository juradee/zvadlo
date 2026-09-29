<?php

declare(strict_types=1);

namespace App\Model\Skautis;

use App\Model\Skautis\Dto\SkautisRole;
use App\Model\Skautis\Dto\SkautisUser;
use App\Model\Skautis\Dto\Unit;
use Skaut\Skautis\Skautis;
use Skaut\Skautis\UnexpectedValueException;
use Skaut\Skautis\Wsdl\AuthenticationException;
use Skaut\Skautis\Wsdl\WebServiceName;
use Skaut\Skautis\Wsdl\WsdlException;
use stdClass;

final class SoapSkautisGateway implements SkautisGateway
{
    /** Extend the login when less than this many seconds remain. */
    private const int REFRESH_BEFORE_EXPIRY = 20 * 60;

    public function __construct(private readonly Skautis $skautis)
    {
    }

    public function getLoginUrl(string $backlink = ''): string
    {
        return $this->skautis->getLoginUrl($backlink);
    }

    public function getLogoutUrl(): string
    {
        return $this->skautis->getLogoutUrl();
    }

    public function login(array $post): void
    {
        try {
            $this->skautis->setLoginData($post);
        } catch (UnexpectedValueException $exception) {
            throw new SkautisException('skautIS did not send valid login data.', 0, $exception);
        }
    }

    public function isLoggedIn(): bool
    {
        $user = $this->skautis->getUser();

        try {
            if (! $user->isLoggedIn()) {
                return false;
            }

            $logoutDate = $user->getLogoutDate();
            if ($logoutDate !== null && $logoutDate->getTimestamp() - time() < self::REFRESH_BEFORE_EXPIRY) {
                $user->updateLogoutTime();
            }

            return true;
        } catch (AuthenticationException) {
            return false;
        } catch (WsdlException $exception) {
            throw $this->wrap($exception);
        }
    }

    public function logout(): void
    {
        $this->skautis->getUser()->resetLoginData();
    }

    public function getRoleId(): ?int
    {
        return $this->skautis->getUser()->getRoleId();
    }

    public function getUnitId(): ?int
    {
        return $this->skautis->getUser()->getUnitId();
    }

    public function getCurrentUser(): SkautisUser
    {
        $detail = $this->object($this->call(WebServiceName::USER_MANAGEMENT, 'UserDetail'));

        return new SkautisUser(
            self::int($detail, 'ID'),
            self::int($detail, 'ID_Person'),
            self::string($detail, 'UserName'),
            self::string($detail, 'Person'),
        );
    }

    public function getUserRoles(int $userId): array
    {
        $roles = [];
        foreach ($this->objects($this->call(WebServiceName::USER_MANAGEMENT, 'UserRoleAll', ['ID_User' => $userId, 'IsActive' => true])) as $role) {
            $roles[] = new SkautisRole(
                self::int($role, 'ID'),
                self::string($role, 'Key'),
                self::string($role, 'Role'),
                self::int($role, 'ID_Unit'),
                self::string($role, 'Unit'),
                self::string($role, 'DisplayName'),
            );
        }

        return $roles;
    }

    public function switchRole(int $userRoleId): int
    {
        $result = $this->object($this->call(WebServiceName::USER_MANAGEMENT, 'LoginUpdate', [
            'ID' => $this->skautis->getUser()->getLoginId(),
            'ID_UserRole' => $userRoleId,
        ]));
        $unitId = self::int($result, 'ID_Unit');

        $this->skautis->getUser()->updateLoginData(null, $userRoleId, $unitId);

        return $unitId;
    }

    public function getUnit(int $unitId): ?Unit
    {
        $unit = $this->call(WebServiceName::ORGANIZATION_UNIT, 'UnitDetail', ['ID' => $unitId]);

        return $unit instanceof stdClass ? $this->unit($unit) : null;
    }

    public function getChildUnits(int $unitId): array
    {
        $units = [];
        foreach ($this->objects($this->call(WebServiceName::ORGANIZATION_UNIT, 'UnitAll', ['ID_UnitParent' => $unitId])) as $unit) {
            $units[] = $this->unit($unit);
        }

        return $units;
    }

    private function unit(stdClass $unit): Unit
    {
        $parentId = $unit->ID_UnitParent ?? null;

        return new Unit(
            self::int($unit, 'ID'),
            self::string($unit, 'DisplayName'),
            self::string($unit, 'RegistrationNumber'),
            self::string($unit, 'ID_UnitType'),
            is_numeric($parentId) ? (int) $parentId : null,
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function call(string $service, string $method, array $arguments = []): mixed
    {
        try {
            return $this->skautis->getWebService($service)->call($method, [$arguments]);
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (WsdlException $exception) {
            throw $this->wrap($exception);
        }
    }

    private function wrap(WsdlException $exception): SkautisException
    {
        return new SkautisException('skautIS call failed: '.$exception->getMessage(), 0, $exception);
    }

    private function object(mixed $result): stdClass
    {
        if (! $result instanceof stdClass) {
            throw new SkautisException('skautIS returned no record where one was expected.');
        }

        return $result;
    }

    /**
     * @return list<stdClass>
     */
    private function objects(mixed $result): array
    {
        if (! \is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn (mixed $item): bool => $item instanceof stdClass));
    }

    private static function int(stdClass $object, string $field): int
    {
        $value = $object->$field ?? null;
        if (! is_numeric($value)) {
            throw new SkautisException("skautIS response is missing the numeric field $field.");
        }

        return (int) $value;
    }

    private static function string(stdClass $object, string $field): string
    {
        $value = $object->$field ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }
}
