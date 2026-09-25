# AGAPORA Chick Image Setup (OpenRouter + Hugging Face fallback)

## Security

- Store `OPENROUTER_API_KEY` and `HF_TOKEN` **only** in `backend/.env`.
- Never put keys in React, Vite (`VITE_*`), Git, seeders, or API responses.
- React calls Laravel (`POST /api/outcomes/generate-image`); Laravel calls OpenRouter.

## Configure

1. Create an OpenRouter key: https://openrouter.ai/keys
2. In `backend/.env`:

```env
OPENROUTER_API_KEY=YOUR_OPENROUTER_API_KEY
OPENROUTER_IMAGE_MODEL=google/gemini-3-pro-image
OFFSPRING_IMAGE_ENABLED=true
OFFSPRING_IMAGE_AUTO=false
OFFSPRING_IMAGE_PROVIDER=openrouter
OFFSPRING_IMAGE_FALLBACK_PROVIDER=huggingface
HF_TOKEN=YOUR_NEW_HUGGING_FACE_TOKEN
```

3. Run:

```bash
cd backend
php artisan config:clear
php artisan migrate
php artisan storage:link
```

## Flow

RBGIA / GICA calculate outcomes → Egg/Chick card → Laravel builds prompt → OpenRouter draws image → URL returned to React.

If OpenRouter returns a credit/quota error, AGAPORA switches to Hugging Face for the rest of the cache window (`OPENROUTER_EXHAUSTED_TTL`, default 6 hours).

Split/hidden genes are listed in the prompt but must not be drawn as visible mutations.

## Endpoints

- `POST /api/outcomes/generate-image`
- `POST /api/computation-results/{id}/generate-all-chick-images`

Images are stored under `storage/app/public/chicks/` and cached by genetic signature in `chick_outcome_images`.
