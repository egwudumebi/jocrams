<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MediaFile;
use App\Support\Events\EventPresenter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventBannerController extends Controller
{
    public function __invoke(Event $event)
    {
        if ($event->status !== 'published' && ! request()->user()?->hasPermission('events.manage')) {
            throw new NotFoundHttpException('Event banner not found.');
        }

        $media = $event->featuredImage;

        if (! $media instanceof MediaFile) {
            throw new NotFoundHttpException('Event banner not found.');
        }

        if (! Storage::disk($media->disk)->exists($media->path)) {
            throw new NotFoundHttpException('Stored event banner is unavailable.');
        }

        return response()->file(Storage::disk($media->disk)->path($media->path));
    }
}
