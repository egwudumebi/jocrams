<?php

namespace App\Enums;

enum JournalSubmissionStatus: string
{
    case PaymentPending = 'payment_pending';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RevisionRequested = 'revision_requested';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
