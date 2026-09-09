<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NotificationTemplate::query()->latest();

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:notification_templates,slug'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = NotificationTemplate::query()->create($data);

        return response()->json(['data' => $template], 201);
    }

    public function show(NotificationTemplate $template): JsonResponse
    {
        return response()->json(['data' => $template]);
    }

    public function update(Request $request, NotificationTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', Rule::in(['email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->update($data);

        return response()->json(['data' => $template->fresh()]);
    }

    public function destroy(NotificationTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }
}
