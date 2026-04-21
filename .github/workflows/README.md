# GitHub Actions CI — Required Secrets

Go to **Settings → Secrets and variables → Actions** in your GitHub repo and add:

| Secret | How to generate | Purpose |
|---|---|---|
| `JWT_CHALLENGE_SECRET` | `openssl rand -hex 64` | Signs JWT challenge tokens used in the auth flow |
| `TELEGRAM_BOT_TOKEN` | From [@BotFather](https://t.me/BotFather) | Authenticates outgoing Telegram API calls |
| `TELEGRAM_WEBHOOK_SECRET` | `openssl rand -hex 32` | Verifies incoming webhook requests from Telegram |
| `SEED_ADMIN_PASSWORD` | Any strong string | Password set for the seeded admin user in test runs |
| `POSTMAN_API_KEY` | [Postman account settings](https://go.postman.co/settings/me/api-keys) | (Optional) Used if you publish collection updates via CI |

## Notes

- **Railway deployment** is NOT triggered by this workflow. Railway's native GitHub integration handles auto-deploy on push to `main`. CI only acts as a quality gate.
- The `postman` job is skipped automatically when `Postman/collection.json` is absent (`if: hashFiles(...) != ''`).
- Add `Postman/collection.json` and `Postman/environment.json` to unlock the Newman job.
