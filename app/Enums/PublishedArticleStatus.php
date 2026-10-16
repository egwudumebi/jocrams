<?php

namespace App\Enums;

enum PublishedArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
