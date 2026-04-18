<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MailTemplateController extends Controller
{
    public function __construct(
        private readonly MailTemplateService $mailTemplateService,
    ) {
    }

    public function index(): JsonResponse
    {
        $list = $this->mailTemplateService->listAll();
        return response()->json($list);
    }

    public function show(string $type): JsonResponse
    {
        $config = config('mail_templates.types', []);
        $types = is_array($config) ? array_keys($config) : [];
        if (! in_array($type, $types, true)) {
            return response()->json([ 'message' => 'Mail type not found.' ], 404);
        }
        $locales = $this->mailTemplateService->getTemplateByType($type);
        $placeholders = $this->mailTemplateService->getPlaceholdersForType($type);
        $configValue = config("mail_templates.types.{$type}");
        $name = is_string($configValue) ? $configValue : $type;
        return response()->json([
            'type'        => $type,
            'name'        => $name,
            'locales'     => $locales,
            'placeholders' => $placeholders,
        ]);
    }

    public function update(Request $request, string $type): JsonResponse
    {
        $config = config('mail_templates.types', []);
        $types = is_array($config) ? array_keys($config) : [];
        if (! in_array($type, $types, true)) {
            return response()->json([ 'message' => 'Mail type not found.' ], 404);
        }
        $validated = $request->validate([
            'locales' => 'required|array',
            'locales.en' => 'sometimes|array',
            'locales.en.subject' => 'required_with:locales.en|string|max:255',
            'locales.en.body' => 'required_with:locales.en|string',
            'locales.kk' => 'sometimes|array',
            'locales.kk.subject' => 'required_with:locales.kk|string|max:255',
            'locales.kk.body' => 'required_with:locales.kk|string',
            'locales.ru' => 'sometimes|array',
            'locales.ru.subject' => 'required_with:locales.ru|string|max:255',
            'locales.ru.body' => 'required_with:locales.ru|string',
        ]);
        $this->mailTemplateService->updateTemplate($type, $validated['locales']);
        return response()->json([ 'message' => 'Mail template updated successfully.' ]);
    }
}
