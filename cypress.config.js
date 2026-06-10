import { defineConfig } from 'cypress'
import { execSync } from 'child_process'

export default defineConfig({
  e2e: {
    baseUrl: 'http://localhost:8000/api',
    supportFile: 'cypress/support/e2e.js',
    specPattern: 'cypress/e2e/**/*.cy.js',
    setupNodeEvents(on, config) {
      on('task', {
        async php({ command }) {
          return execSync(`php ${command}`, {
            cwd: config.projectRoot,
            encoding: 'utf-8',
          }).trim()
        },
        async artisan({ command }) {
          return execSync(`php artisan ${command}`, {
            cwd: config.projectRoot,
            encoding: 'utf-8',
          }).trim()
        },
      })
    },
  },
})
