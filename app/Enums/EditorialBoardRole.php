<?php

namespace App\Enums;

enum EditorialBoardRole: string
{
    case EditorInChief = 'Editor-in-Chief';
    case DeputyEditor = 'Deputy Editor';
    case ManagingEditor = 'Managing Editor';
    case ExecutiveEditor = 'Executive Editor';
    case AssociateEditor = 'Associate Editor';
    case SectionEditor = 'Section Editor';
    case GuestEditor = 'Guest Editor';
    case SeniorEditor = 'Senior Editor';
    case EditorialBoardMember = 'Editorial Board Member';
    case AdvisoryBoardMember = 'Advisory Board Member';
    case EditorEmeritus = 'Editor Emeritus';
    case CopyEditor = 'Copy Editor';
    case LanguageEditor = 'Language Editor';
    case StatisticalAdvisor = 'Statistical Advisor';
    case EthicsAdvisor = 'Ethics Advisor';
    case PeerReviewEditor = 'Peer Review Editor';
    case PublicationsManager = 'Publications Manager';
    case ProductionEditor = 'Production Editor';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
