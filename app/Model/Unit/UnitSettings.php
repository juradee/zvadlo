<?php

declare(strict_types=1);

namespace App\Model\Unit;

use App\Model\Mailing\GoogleOAuth;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a unit sets once for all its events.
 */
#[ORM\Entity]
#[ORM\Table(name: 'unit_settings')]
class UnitSettings
{
    public const string DEFAULT_AGREEMENT = 'Potvrzuji, že uvedené údaje jsou pravdivé a že zákonný zástupce souhlasí s účastí na akci.';

    #[ORM\Id]
    #[ORM\Column]
    private int $unitId;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $agreementText = null;

    #[ORM\ManyToOne(targetEntity: GoogleOAuth::class)]
    #[ORM\JoinColumn(name: 'default_oauth_id', nullable: true, onDelete: 'SET NULL')]
    private ?GoogleOAuth $defaultMailAccount = null;

    public function __construct(int $unitId)
    {
        $this->unitId = $unitId;
    }

    public function edit(?string $agreementText, ?GoogleOAuth $defaultMailAccount): void
    {
        $agreementText = $agreementText === null ? null : trim($agreementText);
        $this->agreementText = $agreementText === '' ? null : $agreementText;
        $this->defaultMailAccount = $defaultMailAccount;
    }

    public function getUnitId(): int
    {
        return $this->unitId;
    }

    public function getAgreementText(): string
    {
        return $this->agreementText ?? self::DEFAULT_AGREEMENT;
    }

    public function getDefaultMailAccount(): ?GoogleOAuth
    {
        return $this->defaultMailAccount;
    }
}
