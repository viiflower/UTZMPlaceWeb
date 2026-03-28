const { defineConfig } = require('playwright/test')

module.exports = defineConfig({
  testDir: './tests',
  timeout: 60_000,
  use: {
    baseURL: 'http://127.0.0.1:8080',
    headless: true,
  },
  webServer: {
    command: 'php -S 127.0.0.1:8080 -t .',
    port: 8080,
    reuseExistingServer: true,
    timeout: 120_000,
  },
})
