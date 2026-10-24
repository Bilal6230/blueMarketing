<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPage extends Model
{
    protected $fillable = ['theme', 'content', 'sections', 'projects_mode', 'project_ids', 'draft_payload', 'published_at', 'section_order', 'project_overrides'];
    protected $casts = ['content' => 'array', 'sections' => 'array', 'project_ids' => 'array', 'draft_payload' => 'array', 'published_at' => 'datetime', 'section_order' => 'array', 'project_overrides' => 'array'];

    public const DEFAULT_CONTENT = [
        'hero_eyebrow' => 'Your property journey starts here',
        'hero_title' => 'A better place to begin your next chapter.',
        'hero_description' => 'Discover property possibilities with a marketing partner that puts clarity, confidence and your goals first.',
        'about_title' => 'Real estate is personal. Your experience should be too.',
        'about_description' => 'Every property decision is connected to a bigger story — a home, a business, a family or a future plan. BlueMarketing is built around making that journey easier to navigate.',
        'services_title' => 'More than property marketing. A partner in the process.',
        'projects_title' => 'What does your next move look like?',
        'cta_title' => 'Your next opportunity starts with one good conversation.',
        'contact_title' => "Tell us what you're looking for.",
        'footer_description' => 'A people-first approach to real estate discovery, marketing and customer guidance.',
        'hero_image' => '',
        'about_image' => '',
        'contact_phone' => '',
        'contact_whatsapp' => '',
        'contact_email' => '',
    ];

    public const SECTION_NAMES = ['services'=>'Services', 'about'=>'About us', 'opportunities'=>'Projects / opportunities', 'process'=>'How it works', 'faq'=>'FAQs', 'contact'=>'Contact'];

    public function orderedSections(): array
    {
        $valid = array_keys(self::SECTION_NAMES);
        $saved = array_values(array_intersect($this->section_order ?? [], $valid));
        return array_values(array_unique(array_merge($saved, $valid)));
    }

    public function projectOverride(int $projectId, string $key): string
    {
        return (string) (($this->project_overrides ?? [])[$projectId][$key] ?? '');
    }

    public function contentValue(string $key): string
    {
        return (string) (($this->content ?? [])[$key] ?? self::DEFAULT_CONTENT[$key] ?? '');
    }

    public function sectionEnabled(string $key): bool
    {
        return (bool) (($this->sections ?? [])[$key] ?? true);
    }
}
