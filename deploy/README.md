# Termy deployment runbook (Ubuntu 24.04, single EC2 host)

Assumes nginx, php8.3-fpm (+ phpredis), MySQL and Redis are installed. There is no Node on the server:
the frontend is built on the developer PC and uploaded.

## 1. First-time server setup

If the repository is private, the server needs read access first. Create a read-only deploy key on
the server (`ssh-keygen -t ed25519 -f ~/.ssh/termy_deploy -N ""`), add `~/.ssh/termy_deploy.pub`
under the repository's Settings > Deploy keys, and use the SSH clone URL with
`GIT_SSH_COMMAND="ssh -i ~/.ssh/termy_deploy"` (or a matching `~/.ssh/config` entry on the server).

```bash
sudo mkdir -p /var/www && sudo chown ubuntu:ubuntu /var/www
git clone <repo-url> /var/www/termy && cd /var/www/termy
git checkout <branch>        # until the work is merged to main, e.g. feat/cloud-ready
mkdir -p frontend/dist
cp deploy/backend.env.production.example backend/.env
nano backend/.env            # set APP_URL, FRONTEND_URL, DB_PASSWORD, ...
```

Create the MySQL database and user matching `backend/.env` (`termy` / `termy`).

```bash
cd backend && composer install --no-dev -o && php artisan key:generate && cd ..
bash deploy/deploy.sh
```

## 2. nginx

```bash
sudo cp deploy/nginx/termy.conf /etc/nginx/sites-available/termy
sudo nano /etc/nginx/sites-available/termy     # set server_name to the real domain
sudo ln -s /etc/nginx/sites-available/termy /etc/nginx/sites-enabled/termy
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Uploads: raise `upload_max_filesize` and `post_max_size` to `60M` in `/etc/php/8.3/fpm/php.ini`
(display videos reach 50 MB), then `sudo systemctl reload php8.3-fpm`.

## 3. HTTPS

```bash
sudo certbot --nginx -d turnos.example.com
```

certbot rewrites the site file (TLS listener, certificates, redirect). Keep `APP_URL` and
`FRONTEND_URL` on `https://`.

## 4. Build and upload the frontend (developer PC)

```bash
cd frontend && npm ci && npm run build      # VITE_API_URL defaults to /api
scp -r dist/* termy:/var/www/termy/frontend/dist/
```

`termy` is the SSH alias for the server. Repeat after every frontend change.

## 5. First admin user (without the seeder)

On the server, in `/var/www/termy/backend`:

```bash
php artisan tinker
```

```php
App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@your-domain.com',
    'password' => 'a-strong-password',   // hashed automatically by the model cast
    'role' => 'admin',                   // enum: admin | operator
]);
```

`services` is nullable; leaving it empty means the user can attend all services.

## WARNING: never run the seeder in production

`php artisan db:seed` creates sample users with a default password. `deploy.sh` never runs it.

## Updating

`bash /var/www/termy/deploy/deploy.sh` pulls, installs, migrates, caches and reloads services.
Upload a new `frontend/dist` separately when the frontend changed.
