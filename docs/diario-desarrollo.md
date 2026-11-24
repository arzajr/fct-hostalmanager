## 25 de noviembre de 2026 · Creación de la aplicación base

Creé la aplicación base de Laravel 13 en `aplicacion/` usando Composer desde Docker.
Antes de guardarla, comprobé con `git add --dry-run` qué archivos incluiría Git.
Verifiqué que se incluían `.env.example` y `composer.lock`, y que `.env` y `vendor/`
quedaban excluidos.

Commit de referencia: `febe555`.