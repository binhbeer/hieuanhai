---
name: genanh-api-workflow
description: Work on GenAnh AI image API, API keys, quota, request logging, upload validation, and image result URLs. Use when editing AiImageController, AiImageEditor, AiApiKey, AiApiRequest, routes/api.php, quota UI, or AI image feature tests.
---

# GenAnh AI API Workflow

## Flow
- Public app domain: `https://genanh.com`; API endpoint base: `https://genanh.com/api`.
1. `POST /api/ai/images` enters `routes/api.php` with `throttle:ai-api` and `ai.api.key`.
2. Middleware stores validated key on `$request->attributes['ai_api_key']`.
3. `AiImageController::store()` validates prompt/uploads, checks quota, calls `AiImageEditor::create()`.
4. Quota increments only after successful create.
5. Every attempt writes `AiApiRequest` with status, duration, quota flag, request meta, and response meta.

## Guardrails
- Do not charge quota on validation failure, auth failure, provider failure, or thrown exception.
- Keep upload validation bounded by `config('ai.image_max_reference_photos')` and `config('ai.image_upload_max_kb')`.
- Keep response errors safe: no stack traces, secrets, raw provider payloads, or filesystem paths.
- Preserve Vietnamese API/user messages unless changing copy is requested.
- Add/update `tests/Feature/AiImageApiTest.php` for auth, quota, validation, and success-path changes.

## Useful checks
```bash
docker compose exec app sh -lc 'cd /var/www/html && php artisan test tests/Feature/AiImageApiTest.php'
docker compose exec app sh -lc 'cd /var/www/html && composer lint:check'
docker compose exec app sh -lc 'cd /var/www/html && composer types:check'
```
