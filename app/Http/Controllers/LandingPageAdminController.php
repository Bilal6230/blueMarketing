<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LandingPageAdminController extends Controller
{
    public function index()
    {
        return view('admin.landing-page.index', [
            'title' => 'Landing Page Manager',
            'page' => LandingPage::first() ?? new LandingPage(),
            'projects' => Project::orderBy('project')->get(['id', 'project', 'address']),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['corporate', 'luxury', 'vibrant'])],
            'projects_mode' => ['required', Rule::in(['default', 'actual'])],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'distinct', Rule::exists('projects', 'id')],
            'content' => ['required', 'array'],
            'content.hero_image' => ['nullable', 'url', 'max:2048', 'starts_with:https://'],
            'content.about_image' => ['nullable', 'url', 'max:2048', 'starts_with:https://'],
            'sections' => ['nullable', 'array'],
        ]);

        $content = [];
        foreach (LandingPage::DEFAULT_CONTENT as $key => $fallback) {
            $value = $validated['content'][$key] ?? '';
            if (!is_string($value)) {
                return back()->withErrors(["content.$key" => 'Invalid field value.'])->withInput();
            }
            if (!in_array($key, ['hero_image', 'about_image'], true) && mb_strlen($value) > 1200) {
                return back()->withErrors(["content.$key" => 'Maximum 1200 characters.'])->withInput();
            }
            $content[$key] = trim($value) !== '' ? trim($value) : $fallback;
        }

        $sections = [];
        foreach (LandingPage::SECTION_NAMES as $key => $label) {
            $sections[$key] = ($request->input('sections.'.$key) === '1');
        }

        DB::transaction(function () use ($validated, $content, $sections) {
            $page = LandingPage::first() ?? new LandingPage();
            $page->theme = $validated['theme'];
            $page->projects_mode = $validated['projects_mode'];
            $page->content = $content;
            $page->sections = $sections;
            $page->project_ids = $validated['project_ids'] ?? [];
            $page->save();
        });

        return redirect()->route('landing-page.admin.index')->with('success', 'Landing page settings saved successfully.');
    }
}
