<?php

declare(strict_types=1);

namespace App\Model\Registration;

/**
 * What unlocks the personal link (spec ch. 3).
 */
enum VerificationMethod
{
    /** A member: the participant's birth date. */
    case BirthDate;
    /** A non-member before the first answer: the e-mail the invitation went to. */
    case Email;
    /** A non-member with data already filled in: a one-time code sent by e-mail (phase 3). */
    case Code;
    /** Nothing to verify against (a member without a birth date in skautIS). */
    case None;
}
