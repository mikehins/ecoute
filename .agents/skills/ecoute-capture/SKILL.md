---
name: ecoute-capture
description: Capture DOM context and transform it into structured GitHub issues via AI. Use when reporting bugs, UX problems, content issues, or any page feedback that needs developer attention.
---

# Ecoute Capture

Ecoute lets you click any element on a page, describe the problem, and have AI turn it into a structured GitHub issue — complete with suggested fix and code changes.

## When to Use This Skill

Activate this skill whenever:
- You spot a bug on a page and want to file a detailed, AI-enriched issue
- You notice a UX problem (layout, spacing, interaction)
- Content is wrong, outdated, or missing
- Performance or accessibility issues are visible
- Any feedback that needs to reach developers with full DOM context

## How It Works

1. **Activate**: Press `Ctrl+Shift+E` (configurable) — the cursor turns into a crosshair
2. **Select**: Click the problematic element on the page
3. **Describe**: Type (or dictate via the mic button) what's wrong in the panel
4. **Preview**: Optionally preview the AI-generated issue before submitting
5. **Submit**: Click "Send" — the capture is queued for AI processing

Behind the scenes:
- The element's HTML, CSS selector, data attributes, and nearby text are extracted
- A screenshot of the full page is captured (sensitive fields are masked)
- If diagnostics are enabled, console errors and failed network requests are included
- AI (OpenAI GPT-4o or Anthropic Claude) processes the context and returns a structured JSON: title, description, bug type, suggested fix, and code suggestion
- A GitHub issue is created automatically (if configured), or the structured data is stored for admin review

## Voice Dictation

Instead of typing, click the microphone button in the issue description panel. Your browser will transcribe your speech directly into the textarea. Click again to stop. Works in Chrome and any browser with `SpeechRecognition` support.

## Template Selection

If the app has configured GitHub issue templates, a dropdown appears above the description field. Select a template to structure the AI output into sections matching the template's fields.

## Privacy

- PII (emails, credit card numbers, SSNs) is automatically redacted before sending to any AI provider
- Screenshots mask password fields and `.ecoute-sensitive` elements
- Source-code scanning is opt-in (disabled by default)
- Browser diagnostics (console logs, network requests) are opt-in (disabled by default) and capture only request metadata (URL, method, status, duration) — never bodies, headers, or auth tokens

## Admin Notes

This skill is only available to users who pass the configured `ecoute-admin` Gate. The Gate can be customized in `config/ecoute.php` to match your authorization model (e.g. `fn ($user) => $user->isAdmin()`).
