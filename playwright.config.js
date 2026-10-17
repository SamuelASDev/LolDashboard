import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8765',
        trace: 'retain-on-failure',
        launchOptions: process.env.PLAYWRIGHT_BROWSER_EXECUTABLE
            ? { executablePath: process.env.PLAYWRIGHT_BROWSER_EXECUTABLE } : {},
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } } },
        { name: 'mobile', use: { ...devices['Pixel 5'] } },
    ],
    webServer: {
        command: 'php tests/Browser/prepare.php && php artisan serve --host=127.0.0.1 --port=8765',
        url: 'http://127.0.0.1:8765',
        reuseExistingServer: false,
        timeout: 30000,
        env: {
            APP_ENV: 'testing', APP_DEBUG: 'false', BROWSER_TEST: '1',
            APP_KEY: 'base64:' + Buffer.alloc(32, 1).toString('base64'),
            DB_CONNECTION: 'sqlite', DB_DATABASE: path.resolve('storage/framework/testing/browser.sqlite'), DB_URL: '',
            CACHE_STORE: 'array', SESSION_DRIVER: 'cookie', SESSION_COOKIE: 'lol_browser_test',
            MAIL_MAILER: 'array', RIOT_QUEUE_CONNECTION: 'database', RIOT_KEY: '',
        },
    },
});
