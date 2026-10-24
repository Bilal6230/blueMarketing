<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LandingPageAdminController extends Controller
{
    private function record(): LandingPage
    {
        return LandingPage::first() ?? new LandingPage();
    }

    private function draftPage(LandingPage $record): LandingPage
    {
        $draft = clone $record;
        if (is_array($record->draft_payload)) {
            $draft->fill($record->draft_payload);
        }
        return $draft;
    }

    public function index()
    {
        $record = $this->record();
        return view('admin.landing-page.index', [
            'title' => 'Landing Page Manager',
            'page' => $this->draftPage($record),
            'hasDraft' => !is_null($record->draft_payload),
            'projects' => Project::orderBy('project')->get(['id', 'project', 'address']),
        ]);
    }

    private function normalizeImageUrl(string $image): string
    {
        $image = trim($image);
        if ($image === '') return '';

        // Uploads must be stored as origin-independent paths, so local :8001 and production both work.
        $urlPath = parse_url($image, PHP_URL_PATH);
        if (is_string($urlPath) && preg_match('~^/storage/landing-page/[A-Za-z0-9_-]+\\.(?:jpg|jpeg|png|webp)$~i', $urlPath)) {
            // Only accept absolute upload URLs from this application or the previously configured app origin.
            $host = parse_url($image, PHP_URL_HOST);
            $allowedHosts = array_filter([
                request()->getHost(),
                parse_url(config('app.url'), PHP_URL_HOST),
            ]);
            if (str_starts_with($image, '/') || ($host && in_array(strtolower($host), array_map('strtolower', $allowedHosts), true))) {
                return $urlPath;
            }
        }
        if (filter_var($image, FILTER_VALIDATE_URL) && parse_url($image, PHP_URL_SCHEME) === 'https'
            && !preg_match('~[\x00-\x1f"<>]~', $image)) {
            return $image;
        }
        throw \Illuminate\Validation\ValidationException::withMessages([
            'image' => 'Use a valid HTTPS image URL or an image uploaded through the Landing Page Manager.'
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['corporate', 'luxury', 'vibrant'])],
            'projects_mode' => ['required', Rule::in(['default', 'actual'])],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'distinct', Rule::exists('projects', 'id')],
            'content' => ['required', 'array'],
            'content.hero_image' => ['nullable', 'string', 'max:2048'],
            'content.about_image' => ['nullable', 'string', 'max:2048'],
            'sections' => ['nullable', 'array'],
            'section_order' => ['nullable', 'array'],
            'section_order.*' => ['string', Rule::in(array_keys(LandingPage::SECTION_NAMES)), 'distinct'],
            'project_overrides' => ['nullable', 'array'],
            'project_overrides.*.title' => ['nullable', 'string', 'max:150'],
            'project_overrides.*.description' => ['nullable', 'string', 'max:600'],
            'project_overrides.*.image' => ['nullable', 'string', 'max:2048'],
        ]);

        $content = [];
        foreach (LandingPage::DEFAULT_CONTENT as $key => $fallback) {
            $value = $validated['content'][$key] ?? '';
            if (!is_string($value)) {
                throw \Illuminate\Validation\ValidationException::withMessages(["content.$key" => 'Invalid field value.']);
            }
            if (!in_array($key, ['hero_image', 'about_image'], true) && mb_strlen($value) > 1200) {
                throw \Illuminate\Validation\ValidationException::withMessages(["content.$key" => 'Maximum 1200 characters.']);
            }
            if (in_array($key, ['hero_image', 'about_image'], true)) {
                $value = $this->normalizeImageUrl($value);
            }
            $content[$key] = trim($value) !== '' ? trim($value) : $fallback;
        }

        $sections = [];
        foreach (LandingPage::SECTION_NAMES as $key => $label) {
            $sections[$key] = $request->input('sections.'.$key) === '1';
        }

        $order = array_values(array_unique(array_merge(
            $validated['section_order'] ?? [],
            array_keys(LandingPage::SECTION_NAMES)
        )));
        $overrides = [];
        foreach (($validated['project_overrides'] ?? []) as $id => $item) {
            if (!ctype_digit((string) $id) || !Project::whereKey($id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['project_overrides' => 'Invalid project selected.']);
            }
            $image = trim((string) ($item['image'] ?? ''));
            $image = $this->normalizeImageUrl($image);
            $overrides[$id] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'description' => trim((string) ($item['description'] ?? '')),
                'image' => $image,
            ];
        }

        return [
            'section_order' => $order,
            'project_overrides' => $overrides,
            'theme' => $validated['theme'],
            'projects_mode' => $validated['projects_mode'],
            'project_ids' => $validated['project_ids'] ?? [],
            'content' => $content,
            'sections' => $sections,
        ];
    }

    public function update(Request $request)
    {
        $payload = $this->validatedPayload($request);

        DB::transaction(function () use ($payload) {
            $page = LandingPage::query()->lockForUpdate()->first() ?? new LandingPage();
            $page->draft_payload = $payload;
            $page->save();
        });

        return redirect()->route('landing-page.admin.index')->with('success', 'Draft saved. Public website has not changed.');
    }

    public function preview()
    {
        $record = $this->record();
        $landingPage = $this->draftPage($record);
        return response()->view('welcome', ['landingPage' => $landingPage, 'isLandingPreview' => true])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "frame-ancestors 'self'");
    }

    public function publish()
    {
        DB::transaction(function () {
            $page = LandingPage::query()->lockForUpdate()->first();
            if (!$page || !is_array($page->draft_payload)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['draft' => 'Save a draft before publishing.']);
            }
            $payload = $page->draft_payload;
            $page->fill($payload);
            $page->draft_payload = null;
            $page->published_at = now();
            $page->save();
        });

        return redirect()->route('landing-page.admin.index')->with('success', 'Landing page published successfully.');
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'slot' => ['required', Rule::in(['hero_image', 'about_image', 'project_image'])],
            'project_id' => ['required_if:slot,project_image', 'nullable', 'integer', Rule::exists('projects', 'id')],
        ]);

        // Laravel public disk uses a dedicated folder. Never allow executable uploads.
        $file = $request->file('image');
        $path = $file->store('landing-page', 'public');

        $page = LandingPage::query()->first() ?? new LandingPage();
        $draft = $this->draftPage($page);
        $payload = [
            'theme' => $draft->theme ?: 'corporate',
            'projects_mode' => $draft->projects_mode ?: 'default',
            'project_ids' => $draft->project_ids ?? [],
            'sections' => $draft->sections ?? [],
            'section_order' => $draft->section_order ?? [],
            'project_overrides' => $draft->project_overrides ?? [],
            'content' => $draft->content ?? [],
        ];
        if ($request->slot === 'project_image') {
            $projectId = (int) $request->project_id;
            $payload['project_overrides'][$projectId]['image'] = '/storage/' . $path;
        } else {
            $payload['content'][$request->slot] = Storage::disk('public')->url($path);
        }

        $page->draft_payload = $payload;
        $page->save();

        return redirect()->route('landing-page.admin.index')->with('success', 'Image uploaded to draft. Review and publish when ready.');
    }
}
