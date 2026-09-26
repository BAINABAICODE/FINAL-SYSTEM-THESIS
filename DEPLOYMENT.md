# Deploy AGAPORA on Vercel and Supabase

The site and the Laravel API ship as one Vercel project. Vercel builds `Dockerfile.vercel`, serves the React app, and routes `/api` to Laravel. Supabase hosts the Postgres database and the public files for generated chick images.

Local development does not change. Keep using MySQL in `backend/.env`, `php artisan serve`, and `npm run dev`.

## 1. Create the Supabase project

1. Create a project at [supabase.com](https://supabase.com).
2. Open **Project Settings → Database** and copy the **Session pooler** connection (port **5432**). Use that host for the running app. Do not use port **6543**.
3. The pooler username looks like `postgres.YOUR_PROJECT_REF`. The database name is `postgres`.
4. Open **Project Settings → Storage → S3 Access Keys** and create an access key.
5. In **Storage**, create a **public** bucket named `agapora`.

## 2. Load a copy of the current database

From `backend`, with `.env` still pointed at the local MySQL database:

```powershell
php scripts/export-mysql-for-supabase.php
```

That writes `backend/database/supabase/*.sql`. The files contain your accounts and are gitignored. Each file stays under the SQL Editor size limit. In the Supabase **SQL Editor**, paste one file per query and run them in this order:

1. `00-schema.sql`
2. `01-data-part-01.sql`, then `01-data-part-02.sql`, and so on through the last part
3. `99-finish.sql`

The last file adds foreign keys, resets id sequences, and turns on row-level security so the public Supabase API cannot read these tables. Laravel connects as the database owner and is not blocked by those policies.

Generated chick images are files on this computer, not rows. After the site is live, generate those images again. They will be stored in the `agapora` bucket.

To refresh the copy later, run the export command again and re-run the SQL files. They replace the public tables created by this export.

## 3. Deploy on Vercel

1. Install the [Vercel CLI](https://vercel.com/docs/cli) and Docker Desktop if you want to test the image locally. Git import does not require Docker on this PC.
2. Import this repository as one Vercel project. Leave the root directory as the repository root. Vercel reads `vercel.json` and builds `Dockerfile.vercel`.
3. Generate an application key and add it once. Reuse the same key for later deploys:

```powershell
cd backend
php artisan key:generate --show
```

4. In the Vercel project, add the variables from `backend/.env.production.example`. Fill in the Supabase host, project ref, database password, S3 keys, and the Vercel URL.

`APP_URL` and `CORS_ALLOWED_ORIGINS` must be the final `https://` domain. If the first deploy assigns the domain, set those two variables and redeploy.

5. Deploy. The site is served from `/`, and the API is `/api` on the same domain. The frontend build uses that same-origin path, so the React app does not need its own API URL.

Open `https://YOUR_DOMAIN/up`. A healthy Laravel process returns an OK response.

## Production variables

| Variable | Value |
| --- | --- |
| `APP_NAME` | `AGAPORA` |
| `APP_ENV` | `production` |
| `APP_KEY` | Output of `php artisan key:generate --show` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://YOUR_VERCEL_DOMAIN` |
| `LOG_CHANNEL` | `stderr` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | Session pooler host |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `postgres` |
| `DB_USERNAME` | `postgres.YOUR_PROJECT_REF` |
| `DB_PASSWORD` | Database password |
| `DB_SSLMODE` | `require` |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `FILESYSTEM_PUBLIC_DRIVER` | `s3` |
| `AWS_ACCESS_KEY_ID` | Supabase S3 access key id |
| `AWS_SECRET_ACCESS_KEY` | Supabase S3 secret |
| `AWS_DEFAULT_REGION` | Supabase project region |
| `AWS_BUCKET` | `agapora` |
| `AWS_ENDPOINT` | `https://YOUR_PROJECT_REF.supabase.co/storage/v1/s3` |
| `AWS_URL` | `https://YOUR_PROJECT_REF.supabase.co/storage/v1/object/public/agapora` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` |
| `CORS_ALLOWED_ORIGINS` | `https://YOUR_VERCEL_DOMAIN` |

Image and writing keys (`OPENROUTER_API_KEY`, `HF_TOKEN`, `GROQ_API_KEY`) stay empty until you want those features. They are listed in `backend/.env.production.example`.

## Later schema changes

The SQL copy includes the `migrations` table, so this schema matches the code you exported. After you add a new Laravel migration, run it against Supabase from a machine that has the `pdo_pgsql` PHP extension:

```powershell
php artisan migrate --force
```

Set the Supabase variables in the environment for that command, and do not save them over the local MySQL `.env`.
