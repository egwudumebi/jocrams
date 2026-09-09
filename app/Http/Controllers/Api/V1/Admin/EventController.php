<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EventMemberType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\Event;
use App\Services\Events\EventService;
use App\Support\Events\EventPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            ->with('category')
            ->withCount('registrations')
            ->orderBy('starts_at');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('upcoming')) {
            $query->where('starts_at', '>=', now());
        }

        $paginator = $query->paginate(20)->through(
            fn (Event $event) => EventPresenter::forEvent($event),
        );

        return response()->json($paginator);
    }

    public function memberTypes(): JsonResponse
    {
        return response()->json(['data' => EventMemberType::catalog()]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['banner']);

        $event = $this->eventService->create(
            $request->user(),
            $data,
            $request->file('banner'),
        );

        return response()->json(['data' => EventPresenter::forEvent($event)], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $event->loadCount('registrations');

        return response()->json(['data' => EventPresenter::forEvent($event)]);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();
        unset($data['banner']);

        $event = $this->eventService->update(
            $event,
            $request->user(),
            $data,
            $request->file('banner'),
        );

        return response()->json(['data' => EventPresenter::forEvent($event)]);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->eventService->delete($event);

        return response()->json(['message' => 'Event deleted.']);
    }
}
