<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\FooterColumn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class InstallerController extends Controller
{
    private function requirements(): array
    {
        return [
            'PHP 8.3 or newer' => PHP_VERSION_ID >= 80300,
            'OpenSSL' => extension_loaded('openssl'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'Mbstring' => extension_loaded('mbstring'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'XML' => extension_loaded('xml'),
            'DOM HTML sanitizer' => extension_loaded('dom'),
            'Storage writable' => is_writable(storage_path('app')) && is_writable(storage_path('framework')),
            'Bootstrap cache writable' => is_writable(base_path('bootstrap/cache')),
            'Environment writable' => is_writable(base_path('.env')),
            'Config cache absent' => ! is_file(base_path('bootstrap/cache/config.php')),
        ];
    }

    private function installError(Request $request, string $field, string $message): RedirectResponse
    {
        return back()->withErrors([$field => $message])->withInput($request->only([
            'app_url', 'db_host', 'db_port', 'db_database', 'db_username', 'admin_name', 'admin_email',
        ]));
    }

    public function show(): View
    {
        return view('installer.show', ['requirements' => $this->requirements()]);
    }

    public function install(Request $request): RedirectResponse
    {
        abort_if(is_file(storage_path('app/installed.lock')), 403);

        $data = $request->validate([
            'installation_token' => ['required', 'string'],
            'app_url' => ['required', 'url', 'max:190'],
            'db_host' => ['required', 'string', 'max:190'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:100'],
            'db_username' => ['required', 'string', 'max:100'],
            'db_password' => ['nullable', 'string', 'max:500'],
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:190'],
            'admin_password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $actualToken = (string) env('INSTALL_TOKEN', '');
        if ($actualToken === '' || ! hash_equals($actualToken, $data['installation_token'])) {
            return $this->installError($request, 'installation_token', 'Invalid installation token.');
        }

        if (in_array(false, $this->requirements(), true)) {
            return $this->installError($request, 'requirements', 'Fix the server requirements before continuing.');
        }

        $lock = fopen(storage_path('app/installing.lock'), 'c+');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            return $this->installError($request, 'installer', 'Installer is busy. Try again.');
        }

        try {
            if (is_file(storage_path('app/installed.lock'))) {
                return redirect()->route('login');
            }

            config()->set('database.default', 'mysql');
            config()->set('database.connections.mysql.url', null);
            config()->set('database.connections.mysql.host', $data['db_host']);
            config()->set('database.connections.mysql.port', (string) $data['db_port']);
            config()->set('database.connections.mysql.database', $data['db_database']);
            config()->set('database.connections.mysql.username', $data['db_username']);
            config()->set('database.connections.mysql.password', $data['db_password'] ?? '');
            DB::purge('mysql');

            try {
                DB::connection('mysql')->getPdo();
                // This flow is ONLY for new sites. Existing tables (even without users)
                // may belong to another application or a partially interrupted install.
                if (DB::connection('mysql')->select('SHOW TABLES') !== []) {
                    return $this->installError($request, 'db_database', 'A fresh install requires a completely empty MySQL database. Never reuse or overwrite existing tables.');
                }
            } catch (Throwable $e) {
                report($e);
                return $this->installError($request, 'db_database', 'Unable to connect to the MySQL database. Check the database and user permissions.');
            }

            try {
                Artisan::call('migrate', ['--force' => true]);
                $admin = User::create([
                    'name' => $data['admin_name'],
                    'email' => strtolower(trim($data['admin_email'])),
                    'password' => $data['admin_password'],
                ]);
                $admin->role = 'admin';
                $admin->save();
                foreach (['Marketplace' => '/prompts', 'Account' => '/account', 'Blog' => '/blog', 'Legal' => '/policies'] as $title => $link) {
                    $column = FooterColumn::create(['title'=>$title,'position'=>FooterColumn::count()+1,'enabled'=>true]);
                    $column->links()->create(['label'=>$title === 'Marketplace' ? 'Browse prompts' : ($title === 'Legal' ? 'Marketplace policies' : $title), 'url'=>$link,'position'=>1]);
                }
            } catch (Throwable $e) {
                report($e);
                return $this->installError($request, 'installer', 'Migration or administrator creation failed. Check server logs before retrying.');
            }

            $path = base_path('.env');
            $contents = file_get_contents($path);
            $replace = [
                'APP_NAME' => 'Samith AI',
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => rtrim($data['app_url'], '/'),
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $data['db_host'],
                'DB_PORT' => (string) $data['db_port'],
                'DB_DATABASE' => $data['db_database'],
                'DB_USERNAME' => $data['db_username'],
                'DB_PASSWORD' => $data['db_password'] ?? '',
                'SESSION_DRIVER' => 'file',
                'SESSION_SECURE_COOKIE' => str_starts_with(strtolower($data['app_url']), 'https://') ? 'true' : 'false',
                'CACHE_STORE' => 'file',
                'QUEUE_CONNECTION' => 'sync',
            ];

            foreach ($replace as $key => $value) {
                $value = '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value).'"';
                $pattern = '/^\s*#?\s*'.preg_quote($key, '/').'=.*$/m';
                if (preg_match($pattern, $contents)) {
                    $contents = preg_replace_callback($pattern, fn () => $key.'='.$value, $contents);
                } else {
                    $contents .= "\n".$key.'='.$value."\n";
                }
            }

            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                return $this->installError($request, 'installer', 'Database was initialized, but saving .env failed. Restore writable permissions before retrying.');
            }
            @chmod($path, 0600);
            if (file_put_contents(storage_path('app/installed.lock'), now()->toIso8601String(), LOCK_EX) === false) {
                return $this->installError($request, 'installer', 'Database was initialized, but installation lock could not be written.');
            }

            return redirect()->route('login')->with('success', 'Samith AI installed. Sign in with your administrator account.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
