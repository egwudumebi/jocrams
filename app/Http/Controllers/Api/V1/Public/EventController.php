<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\Events\EventPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->published()->with(['category', 'sessions', 'pricingTiers'])->orderBy('starts_at');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        if ($request->boolean('upcoming', true)) {
            $query->upcoming();
        }

        $paginator = $query->paginate(12)->through(
            fn (Event $event) => EventPresenter::forEvent($event),
        );

        return response()->json($paginator);
    }

    public function show(Event $event): JsonResponse
    {
        abort_if($event->status !== 'published', 404);

        return response()->json(['data' => EventPresenter::forEvent($event)]);
    }
}
