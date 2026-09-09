<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case Dues = 'dues';
    case Registration = 'registration';
    case EventFee = 'event_fee';
    case Certification = 'certification';
    case Donation = 'donation';
    case JournalSubmissionFee = 'journal_submission_fee';
    case JournalPublicationFee = 'journal_publication_fee';
}
